<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\Worker;
use Dtc\QueueBundle\ODM\JobTimingManager;
use PHPUnit\Framework\TestCase;

abstract class BaseJobManagerTest extends TestCase
{
    const PERFORMANCE_TOTAL_JOBS = 100;

    /** @var Worker */
    public static $worker;

    /** @var string */
    public static $jobClass;

    /** @var JobManagerInterface */
    public static $jobManager;

    /** @var RunManager */
    public static $runManager;

    /** @var JobTimingManager */
    public static $jobTimingManager;

    public static function setUpBeforeClass()
    {
        self::$jobClass = self::$jobManager->getJobClass();
        self::$worker->setJobManager(self::$jobManager);
    }

    public function testSaveJob()
    {
        $job = $this->getJob();
        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
        self::$jobManager->deleteJob($job);
    }

    protected function getJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        return $job;
    }

    public function testGetJobByWorker()
    {
        $job = $this->getJob();
        $jobInQueue = self::$jobManager->getJob(self::$worker->getName());
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
    }

    public function testDeleteJob()
    {
        $job = $this->getJob();
        self::$jobManager->deleteJob($job);

        $nextJob = self::$jobManager->getJob();
        self::assertNull($nextJob, "Shouldn't be any jobs left in queue");

        return $job;
    }

    /**
     * Not all managers will support job priority.
     */
    public function testJobPriority()
    {
        $firstJob = new self::$jobClass(self::$worker, false, 12);
        $firstJob->fibonacci(1);
        self::assertNotNull($firstJob->getId(), 'Job id should be generated');

        $secondJob = new self::$jobClass(self::$worker, false, 1);
        $secondJob->fibonacci(1);
        self::assertNotNull($secondJob->getId(), 'Job id should be generated');

        $thirdJob = new self::$jobClass(self::$worker, false, 5);
        $thirdJob->fibonacci(1);
        self::assertNotNull($thirdJob->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $secondJob->getId(),
            $jobInQueue->getId(),
            'Second job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        self::assertEquals(
            $thirdJob->getId(),
            $jobInQueue->getId(),
            'Third job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        self::assertEquals(
            $firstJob->getId(),
            $jobInQueue->getId(),
            'First job id should be returned - lower number unload first'
        );
    }

    public function testResetExceptionJobs()
    {
        $this->expectingException('resetExceptionJobs');
    }

    public function testResetStalledJobs()
    {
        $this->expectingException('resetStalledJobs');
    }

    public function testPruneStalledJobs()
    {
        $this->expectingException('pruneStalledJobs');
    }

    public function testPruneExceptionJobs()
    {
        $this->expectingException('pruneExceptionJobs');
    }

    public function testPruneExpiredJobs()
    {
        $this->expectingException('pruneExpiredJobs');
    }

    public function testGetStatus()
    {
        $this->expectingException('getStatus');
    }

    public function testPruneArchivedJobs()
    {
        $failed = false;
        try {
            $time = time() - 86400;
            self::$jobManager->pruneArchivedJobs(new \DateTime("@$time"));
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }

    /**
     * @param string $method
     */
    protected function expectingException($method)
    {
        $failed = false;
        try {
            self::$jobManager->$method();
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }

    public function performanceEnqueue()
    {
        $jobsTotal = static::PERFORMANCE_TOTAL_JOBS;
        $start = microtime(true);
        for ($i = 0; $i < $jobsTotal; ++$i) {
            self::$worker->later()->fibonacci(1);
        }

        $total = microtime(true) - $start;
        echo "\nTotal of {$jobsTotal} jobs enqueued in {$total} seconds\n";

        try {
            $count = self::$jobManager->getJobCount();
            self::assertEquals($jobsTotal, $count);
        } catch (\Exception $e) {
            if ('Unsupported' !== $e->getMessage()) {
                throw $e;
            }
        }
    }

    /**
     * @outputBuffering disabled
     */
    public function testPerformance()
    {
        echo "\n".static::class.": Testing Performance\n";
        flush();
        $reflection = new \ReflectionObject(self::$jobManager);
        if ($reflection->hasProperty('enableSorting')) {
            $oldEnableSorting = self::$jobManager->enableSorting;
            self::$jobManager->enableSorting = false; // Ignore priority
        }

        // Dequeue all outstanding jobs
        $limit = 10000;
        while ($job = self::$jobManager->getJob() && $limit) {
            $limit -= 1;
        }
        self::assertGreaterThan(0, $limit);

        $this->performanceEnqueue();
        $this->performanceDequeue();

        if ($reflection->hasProperty('enableSorting')) {
            self::$jobManager->enableSorting = $oldEnableSorting; // Ignore priority
        }
    }

    public function performanceDequeue()
    {
        $jobsTotal = static::PERFORMANCE_TOTAL_JOBS;
        $start = microtime(true);
        $job = null;
        for ($i = 0; $i < $jobsTotal; ++$i) {
            $job = self::$jobManager->getJob();
        }
        $total = microtime(true) - $start;
        echo "Total of {$jobsTotal} jobs dequeued in {$total} seconds\n";
        self::assertNotNull($job, 'The last job in queue is null');

        $nextJob = self::$jobManager->getJob();
        self::assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }
}
