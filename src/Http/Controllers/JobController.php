<?php

namespace rodriguezework\VaporUi\Http\Controllers;

use rodriguezework\VaporUi\Http\Requests\JobRequest;
use rodriguezework\VaporUi\Jobs\ForgetFailedJob;
use rodriguezework\VaporUi\Jobs\RetryFailedJob;
use rodriguezework\VaporUi\Repositories\JobsRepository;
use rodriguezework\VaporUi\ValueObjects\Job;
use rodriguezework\VaporUi\ValueObjects\SearchResult;

class JobController
{
    /**
     * Holds an instance of the job repository.
     *
     * @var JobsRepository
     */
    protected $repository;

    /**
     * Creates a new instance of the job controller.
     *
     * @return void
     */
    public function __construct(JobsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Gets the job results by the given request filters.
     *
     * @param  string  $group
     * @return SearchResult
     */
    public function index($group, JobRequest $request)
    {
        return $this->repository->search($group, $request->validated());
    }

    /**
     * Gets a job by the given request filters.
     *
     * @param  string  $group
     * @param  string  $id
     * @return Job|null
     */
    public function show($group, $id, JobRequest $request)
    {
        return $this->repository->get($group, $id, $request->validated());
    }

    /**
     * Retry a job by the given id.
     *
     * @param  string  $id
     * @return void
     */
    public function retry($id)
    {
        dispatch_sync(new RetryFailedJob($id));
    }

    /**
     * Forget a job by the given id.
     *
     * @param  string  $id
     * @return void
     */
    public function forget($id)
    {
        dispatch_sync(new ForgetFailedJob($id));
    }
}
