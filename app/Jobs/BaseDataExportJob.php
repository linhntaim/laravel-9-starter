<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Models\DataExportProvider;
use App\Models\File;
use App\Models\FileProvider;
use App\Support\Exceptions\DatabaseException;
use App\Support\Exceptions\Exception;
use App\Support\Exports\Export;
use Throwable;

trait BaseDataExportJob
{
    protected DataExport $dataExport;

    protected Export $export;

    protected File $file;

    public function __construct(DataExport $dataImport)
    {
        parent::__construct();

        $this->dataExport = $dataImport;
    }

    protected function setExport(Export $export): static
    {
        $this->export = $export;
        return $this;
    }

    protected function getExport(): Export
    {
        return $this->export ?? $this->setExport($this->dataExport->export)->export;
    }

    /**
     * @throws DatabaseException
     * @throws Exception
     */
    protected function handling()
    {
        $export = $this->getExport();
        $this->file = !isset($this->file)
            ? (new FileProvider())->createWithFiler($export(), $export->getName())
            : (new FileProvider())->withModel($this->file)->updateWithFiler($export($this->file));
        if ($export->chunkEnded()) {
            (new DataExportProvider())
                ->withModel($this->dataExport)
                ->updateExported($this->file);
        }
        else {
            self::dispatchWith(
                function ($job) {
                    $job->export = $this->export;
                    $job->file = $this->file;
                    return $job;
                },
                $this->dataExport
            );
        }
    }

    /**
     * @throws DatabaseException
     * @throws Exception
     */
    public function failed(?Throwable $e = null)
    {
        (new DataExportProvider())
            ->withModel($this->dataExport)
            ->updateFailed($e);
    }
}