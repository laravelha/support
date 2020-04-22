<?php

namespace Laravelha\Support\Traits;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

trait Tableable
{
    /**
     * Return array like:
     * [
     *     ['data' => 'columnName', 'searchable' => true, 'orderable' => true, 'linkable' => false]
     * ]
     * searchable and orderable is true by default
     * linkable is false by default
     * @return array[]
     */
    abstract public static function getColumns() : array;

    /**
     * Get datatable
     *
     * @param string $routePrefix
     * @return JsonResponse
     */
    public static function getDatatable(string $routePrefix = ''): JsonResponse
    {
        $columnsCollection = collect(static::getColumns());

        $datas = self::getDatas($columnsCollection);

        $linkables = self::getLinkables($columnsCollection);

        $relations = self::configRelations($datas);

        $list = static::select($datas);

        if(count($relations))
            $list->with($relations);

        $dataTable = Datatables::of($list);

        foreach ($linkables as $linkable) {
            $dataTable->editColumn($linkable, function ($item) use ($linkable, $routePrefix) {
                return '<a href="'.route($routePrefix.static::getRouteName().'.show', [static::getObjectName() => $item]).'">'.$item->$linkable.'</a>';
            });
        }

        $dataTable->rawColumns($linkables);

        return $dataTable->make(true);
    }

    /**
     * Get index data in columns
     *
     * @param  Collection  $columnsCollection
     * @return array
     */
    public static function getDatas(Collection $columnsCollection): array
    {
        return $columnsCollection->map(function ($column) {
            return $column['data'] = strpos($column['data'], '.') ? $column['data'] : static::getTableName().'.'.$column['data'];
        })->toArray();
    }

    /**
     * Return likables columns
     *
     * @param  Collection  $columnsCollection
     * @return array
     */
    public static function getLinkables(Collection $columnsCollection): array
    {
        return $columnsCollection->filter(function ($value) {
            return $value['linkable'] ?? false;
        })->pluck('data')->toArray();
    }

    /**
     * Set data with relation and return relations
     *
     * @param  array  $datas
     * @return array
     */
    public static function configRelations(array &$datas): array
    {
        $relations = [];
        foreach($datas as &$data) {
            $relation = explode('.', $data);

            if($relation[0] != static::getTableName() && count($relation) > 1) {
                $relations[] = $relation[0];
                $data = $relation[0]."_id";
            }
        }

        return $relations;
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

    /**
     * Get table name by the model name
     *
     * @return string
     */
    public static function getRouteName(): string
    {
        return Str::kebab(Str::plural(static::getModelName()));
    }

    /**
     * Get table name by the model name
     *
     * @return string
     */
    public static function getObjectName(): string
    {
        return Str::snake(static::getModelName());
    }
}
