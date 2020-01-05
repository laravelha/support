# Laravelha Support
Package of Support for development on Laravel

## Install
```shell script
composer require laravelha/support
```

## Features
1. Model Traits
    * Tableable
    * RequestQueryBuildable

### How Tableable works
`Tableable` configures models represented in the data table.

#### Requirements
* [laravel-datatables](https://github.com/yajra/laravel-datatables)

#### Step-by-step
1. Import the trait on model.
```php
use Laravelha\Support\Traits\Tableable;

class Modelo extends Model {
    //
}
``` 
2. Add the trait within class.
```php
class Modelo extends Model {
    use Tableable;
    //
}
```
3. Implement the `getColumns` method.
```php
    /**
     * ['data' => 'columnName', 'searchable' => true, 'orderable' => true, 'linkable' => false]
     *
     * searchable and orderable is true by default
     * linkable is false by default
     *
     * @return array[]
     */
    public static function getColumns(): array
    {
        return [
            ['data' => 'id', 'linkable' => true],
            ['data' => 'title'],
            ['data' => 'subtitle'],
            ['data' => 'slug'],
            ['data' => 'content'],
        ];
    }
```
> The options 'searchable' and 'orderable' are 'true' by default, but 'linkable' is 'false', if not informed.

4. On index action get the columns configuration.
```php
public function index()
{
    $columns = Model::getColumns();

    return view('models.index', compact('columns'));
}
```

5. Create data action on controller.
```php
public function data()
{
    return Model::getDatatable();
}
```

6. Create route for data action.
```php
Route::get('/models/data', 'ModelController@data')->name('models.data');
```

7. Create table on index blade.
```blade
<table id="models-table">
    <thead>
        <tr>
            @foreach($columns as $column)
                <th>@lang('models.'.$column['data'])</th>
            @endforeach
        </tr>
    </thead>
</table>
```

8. Config script.
```blade
@push('scripts')
<script id="script">
    $(function () {
        var table = $('#models-table').DataTable({
            serverSide: true,
            processing: true,
            responsive: true,
            order: [ [0, 'desc'] ],
            ajax: {
                url: 'models/data'
            },
            columns: @json($columns),
            pagingType: 'full_numbers'
        });
    });
</script>
@endpush
```

### How RequestQueryBuildable works?
`RequestQueryBuildable` makes behavior of SQL queries by parameters in the request.  

1. Use `?only` separated by commas to filter columns like 'select' in SQL
```
query: only=field1;field2;field3...
```
2. Use `?search` with key and value to apply where or whereHas
```
query: search=key:value

model: 
public static function searchable() {
    'key' => 'operator',
}

```
> define searchable method on model is needed and relationships are identified by dot relation.column
3. Use `?operators` to change search operator dynamically
```
query: field1:operator1
```
4. Use `?order` to sort results
```
query: field:direction
```
5. Use `?with` to load relation data separated by commas
```
query: relation1;relation2;relation3...
```
