<?php

namespace Laravelha\Support\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait RequestQueryBuildable
{
    /**
     * Store columns on select
     *
     * @var array
     */
    private static $select = [];

    /**
     * Store relations
     *
     * @var array
     */
    private static $queryStringRelations = [];

    /**
     * All of the available operators.
     *
     * @var array
     */
    public static $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * @return array
     */
    abstract public static function searchable() : array;

    /**
     * Trait bootable
     * @return void
     */
    public static function bootRequestQueryBuildable(): void
    {
        array_push(self::$select, self::getTableName().'.*');

        self::applyOnly();
        self::applySearch();
        self::applySorts();
        self::getWith();
        self::applyRelations();
    }

    /**
     * Get query string to only 'only=field1;field2;field3'
     *
     * @return array|null
     */
    private static function getOnly() : ?array
    {
        if (! $only = request()->get('only', null))
            return null;

        static::setSelect($only = explode(';', $only));

        self::setQueryStringRelations($only);

        return $only;
    }

    /**
     * Filter columns on select
     *
     * @return void
     */
    private static function applyOnly() : void
    {
        if (! $only = self::getOnly())
            return;

        static::addGlobalScope('only', function (Builder $builder) {
            $builder->select(self::$select);
        });
    }

    /**
     * Filter columns on select
     *
     * @param array $fields
     * @return void
     */
    private static function setSelect(array $fields) : void
    {
        self::$select = [];

        foreach ($fields as $field) {
            if (!strpos($field, '.'))
                array_push(self::$select, $field);
        }
    }

    /**
     * Get query string searchable 'searchable=field1:operator1;field2:operator2;field3:operator3'
     *
     * @return array|null
     */
    private static function getOperators() : ?array
    {
        if (! $operators = request()->get('operators', null))
            return null;

        $operators = explode(';', $operators);

        $fieldOperator = [];
        foreach ($operators as $operator) {
            $parts = explode(':', $operator);

            $fieldOperator[] = $parts[0];
            $fieldOperator[$parts[0]] = $parts[1];
        }

        return $fieldOperator;
    }

    /**
     * Get filed searchable on query string 'search=field1;fie
     *
     * @return array
     */
    private static function getSearch() : array
    {
        $searchBy = request()->get('search', []);

        if (!empty($searchBy)) {
            return explode(';', $searchBy);
        }

        return $searchBy;
    }

    /**
     * Apply searchable as global scope to where
     *
     * @return void
     */
    private static function applySearch() : void
    {
        $searchBy = self::getSearch();

        $searchable = self::getOperators() ?? static::searchable();

        foreach ($searchBy as $item) {
            $item = explode(':', $item);
            if (array_key_exists($item[0], $searchable)) {
                static::addGlobalScope($item[0], function (Builder $builder) use ($item, $searchable) {
                    $operatorValue = static::prepareOperatorValue($searchable[$item[0]], $item[1]);
                    if(!strpos($item[0], '.')) {
                        $builder->where($item[0], $operatorValue['operator'], $operatorValue['value']);
                    } else {
                        $relation = explode('.', $item[0]);
                        $builder->whereHas($relation[0], function ($query) use ($operatorValue, $item, $relation) {
                            $query->where($relation[1], $operatorValue['operator'], $operatorValue['value']);
                        });
                    }
                });
            }
        }
    }

    /**
     * Explode query string sort 'sort=field:direction'
     *
     * @return array
     */
    private static function getSorts() : array
    {
        $sorts = request()->get('sort', []);

        if(!empty($sorts))
            return explode(';', $sorts);

        return $sorts;
    }

    /**
     * Apply global scope to sort (order by)
     *
     * @return void
     */
    private static function applySorts() : void
    {
        if (! $sorts = self::getSorts())
            return;

        foreach ($sorts as $sort) {
            $sort = explode(':', $sort);

            if(strpos($sort[0], '.'))
                continue;

            static::addGlobalScope('sort', function (Builder $builder) use ($sort) {
                $direction = isset($sort[1]) ? $sort[1] : 'asc';
                $builder->orderBy($sort[0], $direction);
            });
        }
    }

    /**
     * @param array $fields
     * @return void
     */
    private static function setQueryStringRelations(array $fields): void
    {
        foreach ($fields as $field) {
            if(strpos($field, '.')) {
                unset(self::$select[$field]);

                $field = explode('.', $field);

                array_push(self::$select, $field[0].'_id');

                if(in_array($field[0], self::$queryStringRelations))
                    array_push(self::$queryStringRelations[$field[0]], $field[1]);
                else
                    self::$queryStringRelations[$field[0]][] = $field[1];
            }
        }
    }

    /**
     * Get query string to with 'with=field1;field2;field3'
     *
     * @return void
     */
    private static function getWith() : void
    {
        if (! $with = request()->get('with', null))
            return;

        $relations = explode(';', $with);

        foreach ($relations as $relation) {
            self::$queryStringRelations[$relation] = [Str::plural(Str::snake($relation)).'.*'];
        }
    }

    /**
     * Apply global scope to relations
     *
     * @return void
     */
    private static function applyRelations() : void
    {
        if (empty($relations = self::$queryStringRelations))
            return;

        static::addGlobalScope('relations', function (Builder $builder) use ($relations) {
            $builder->select(self::$select)
                ->with(self::getWithRelation($relations));
        });
    }

    /**
     * Get operator and value prepared
     *
     * @param array $relations
     * @return array
     */
    private static function getWithRelation(array $relations): array
    {
        foreach ($relations as $relationName => $fields) {
            if (!in_array('id', $fields))
                array_push($fields, 'id');

            $relations[$relationName] = function ($query) use ($fields) {
                $query->select($fields)->withoutGlobalScopes(['only', 'sort', 'relations']);
            };

        }

        return $relations;
    }

    /**
     * Get operator and value prepared
     *
     * @param string $operator
     * @param string $value
     * @return array
     */
    private static function prepareOperatorValue(string $operator, string $value): array
    {
        if (in_array($operator, ['like', 'ilike']))
            $value = "%$value%";

        return compact('operator', 'value');
    }

    /**
     * Get model name without namespace
     *
     * @return string
     */
    public static function getModelName(): string
    {
        return class_basename(get_called_class());
    }

    /**
     * Get table name by the model name
     *
     * @return string
     */
    public static function getTableName(): string
    {
        return Str::plural(Str::snake(static::getModelName()));
    }
}
