<?php

namespace rodriguezework\VaporUi\Repositories;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Illuminate\Support\Str;
use rodriguezework\VaporUi\ValueObjects\Log;
use rodriguezework\VaporUi\ValueObjects\SearchResult;

class LogsRepository
{
    /**
     * The cloud watch logs client.
     *
     * @var CloudWatchLogsClient
     */
    protected $client;

    /**
     * The list of log messages that should be ignored.
     *
     * @var array
     */
    protected $ignore = [
        'Creating storage directory',
        'INIT_START Runtime Version',
        'START RequestId',
        'REPORT RequestId',
        'END RequestId',
        'Executing warming requests',
        'Loaded Composer autoload file',
        'Preparing to add secrets to runtime',
        'Preparing to boot FPM',
        'Preparing to boot Octane',
        'Ensuring ready to start FPM',
        'Starting FPM Process',
        'NOTICE: fpm is running,',
        'NOTICE: ready to handle connections',
        'Caching Laravel configuration',
        'NOTICE: exiting, bye-bye!',
        'NOTICE: Terminating',
        'Injecting secret',
        'Killing container. Container has processed',
        'Downloading the application vendor archive',
        'Loading decrypted environment variables.',
        'Decrypting environment variables.',
    ];

    /**
     * Creates a new instance of the logs repository.
     *
     * @param  CloudWatchLogsClient  $client
     * @return void
     */
    public function __construct(CloudWatchLogsClient $client)
    {
        $this->client = $client;
    }

    /**
     * Gets the log by the given id.
     *
     * @param  string  $group
     * @param  string  $id
     * @param  array  $filters
     * @return Log|null
     */
    public function get($group, $id, $filters = [])
    {
        return $this->search($group, $filters)
            ->entries
            ->firstWhere('id', $id);
    }

    /**
     * Search for the logs by the given filters.
     *
     * @param  string  $group
     * @param  array  $filters
     * @return SearchResult
     */
    public function search($group, $filters = [])
    {
        try {
            $response = $this->client->filterLogEvents(array_filter([
                'logGroupName' => $this->logGroupName($group),
                'limit' => 50,
                'interleaved' => true,
                'nextToken' => $this->nextToken($filters),
                'startTime' => $this->startTime($filters),
                'filterPattern' => $this->filterPattern($filters),
            ]))->toArray();
        } catch (CloudWatchLogsException $e) {
            $resourceNotFoundException = '"__type":"ResourceNotFoundException"';

            if (! Str::contains($e->getMessage(), $resourceNotFoundException)) {
                throw $e;
            }

            $response = [
                'events' => [],
            ];
        }

        $entries = collect($response['events'])
            ->filter(function ($event) use ($filters) {
                return empty($filters['type']) || $filters['type'] === 'timeout' || @json_decode($event['message']);
            })->map(function ($event, $index) use ($group, $filters, &$entries) {
                static $buffer = [];
                static $currentIndex = null;
        
                if (array_key_exists('message', $event) && ($message = json_decode($event['message'], true))) {
                    $event['message'] = $message;
                }
        
                $message = $event['message'];
        
                // Check for "[tracktrace]" in the message
                if (is_string($message) && str_contains($message, '[tracktrace]')) {
                    $currentIndex = $index; // Save the index of the entry before the tracktrace
                    $buffer = []; // Initialize buffer for collecting messages
                    return null; // Skip the current entry
                }
        
                // If current index is set and the message contains a "#"
                if ($currentIndex !== null && is_string($message) && preg_match('/\d+#/', $message)) {
                    $buffer[] = $message; // Add message to buffer
                    return null; // Skip the current entry
                }
        
                // If no "#" is found, finalize the aggregation
                if ($currentIndex !== null && (!is_string($message) || !preg_match('/\d+#/', $message))) {
                    $entries[$currentIndex]['message'] .= '\n' . implode('\n', $buffer); // Append buffered messages to the original index
                    $buffer = []; // Clear the buffer
                    $currentIndex = null; // Reset the current index
                }
        
                return new Log($event, $group, $filters);
            })->filter()->values();

        return new SearchResult($entries, $response['nextToken'] ?? null);
    }

    /**
     * Returns the log group name from the given $group.
     *
     * @param  string  $group
     * @return string
     */
    protected function logGroupName($group)
    {
        $vaporUi = config('vapor-ui');

        $environment = $vaporUi['environment'];

        $usingDockerRuntime = Str::endsWith(
            $_ENV['AWS_LAMBDA_FUNCTION_NAME'] ?? '',
            "$environment-d"
        );

        return sprintf(
            '/aws/lambda/vapor-%s-%s%s%s',
            $vaporUi['project'],
            $environment,
            $usingDockerRuntime ? '-d' : '',
            in_array($group, ['cli', 'queue']) ? "-$group" : ''
        );
    }

    /**
     * Gets the next token from the given $filters.
     *
     * @param  array  $filters
     * @return string|null
     */
    protected function nextToken($filters)
    {
        return isset($filters['cursor']) ? (string) $filters['cursor'] : null;
    }

    /**
     * Gets the start time from the given $filters.
     *
     * @param  array  $filters
     * @return int|null
     */
    protected function startTime($filters)
    {
        return isset($filters['startTime']) ? (int) $filters['startTime'] * 1000 : null;
    }

    /**
     * Gets the filter pattern from the given $filters.
     *
     * @param  array  $filters
     * @return string
     */
    protected function filterPattern($filters)
    {
        $include = '';

        if (array_key_exists('type', $filters)) {
            if ($filters['type'] === 'timeout') {
                $include .= '"Task timed out after" ';
            } else {
                $include .= sprintf('"message" "level_name" "%s" ', strtoupper($filters['type']));
            }
        }

        $query = $filters['query'] ?? '';

        $exclude = $this->ignore
            ? '- "'.collect($this->ignore)->implode('" - "').'"'
            : '';

        $filterPattern = empty($query)
            ? ''
            : collect(explode(' ', $query))
                ->map(function ($term) {
                    return '"'.str_replace('"', '', $term).'"';
                })->implode(' ');

        return $include.$filterPattern.' '.$exclude;
    }
}
