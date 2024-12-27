<?php

namespace rodriguezework\VaporUi\Http\Controllers;

use rodriguezework\VaporUi\Http\Requests\LogRequest;
use rodriguezework\VaporUi\Repositories\LogsRepository;
use rodriguezework\VaporUi\ValueObjects\Log;
use rodriguezework\VaporUi\ValueObjects\SearchResult;

class LogController
{
    /**
     * Holds an instance of the log repository.
     *
     * @var LogsRepository
     */
    protected $repository;

    /**
     * Creates a new instance of the log controller.
     *
     * @param  LogsRepository  $repository
     * @return void
     */
    public function __construct(LogsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Gets the log results by the given request filters.
     *
     * @param  string  $group
     * @param  LogRequest  $request
     * @return SearchResult
     */
    public function index($group, LogRequest $request)
    {
        return $this->repository->search($group, $request->validated());
    }

    /**
     * Gets a log by the given request filters.
     *
     * @param  string  $group
     * @param  string  $id
     * @param  LogRequest  $request
     * @return Log|null
     */
    public function show($group, $id, LogRequest $request)
    {
        return $this->repository->get($group, $id, $request->validated());
    }
}
