<?php

namespace App\Support\Exports;

use App\Support\Exceptions\DatabaseException;
use App\Support\Exceptions\Exception;
use App\Support\Http\Resources\ModelResourceTransformer;
use App\Support\Models\ModelProvider;
use Illuminate\Database\Eloquent\Collection;

abstract class ModelCsvExport extends CsvExport
{
    use ModelResourceTransformer;

    protected array $conditions;

    protected ModelProvider $modelProvider;

    protected int $perRead = 1000;

    protected int $skipDefault = 0;

    protected int $read = 0;

    protected bool $more = true;

    public function __construct(array $conditions = [], int $perRead = 1000)
    {
        $this->data = [];
        $this->conditions = $conditions;
        $this->perRead = $perRead;
        take($this->modelProviderClass(), function ($class) {
            take(new $class, function (ModelProvider $modelProvider) {
                $this->modelProvider = $modelProvider;
            });
        });
    }

    public function conditions(array $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function perRead(int $perRead): static
    {
        $this->perRead = $perRead;
        return $this;
    }

    protected abstract function modelProviderClass(): string;

    protected abstract function modelResourceClass(): string;

    protected function exportBefore($filer)
    {
        parent::exportBefore($filer);
        $this->skipDefault = $this->dataIndex + 1;
        $this->read = 0;
    }

    /**
     * @throws DatabaseException
     * @throws Exception
     */
    protected function prepareData()
    {
        if ($this->more) {
            $this->data = $this->modelResourceTransform(
                with(
                    $this->modelProvider
                        ->limit($this->perRead + 1, (++$this->read - 1) * $this->perRead + $this->skipDefault)
                        ->all($this->conditions),
                    function (Collection $models) {
                        if ($this->more = $models->count() > $this->perRead) {
                            $models->pop();
                        }
                        return $models;
                    }
                ),
                $this->modelResourceClass()
            );
        }
    }

    /**
     * @throws DatabaseException
     * @throws Exception
     */
    protected function data()
    {
        $this->prepareData();
        return parent::data();
    }
}