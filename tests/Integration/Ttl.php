<?php

/**
 * This file is part of the tarantool/queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tarantool\Queue\Tests\Integration;

use Tarantool\Queue\Queue;
use Tarantool\Queue\States;

trait Ttl
{
    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('ttr_1', {ttr = 1})
     */
    public function testTimeToRun() : void
    {
        $queue = $this->getQueue();

        $task1 = $queue->take(.1);
        sleep(1);
        $task2 = $queue->take(.1);

        self::assertTaskInstance($task1);
        self::assertSame('ttr_1', $task1->getData());
        self::assertEquals($task1, $task2);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('ttl_1', {ttl = 1})
     */
    public function testTimeToLive() : void
    {
        sleep(1);
        $task = $this->getQueue()->take(.1);

        self::assertNull($task);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('touch_ttr_1', {ttr = 1})
     */
    public function testTouchTimeToRun() : void
    {
        $queue = $this->getQueue();

        $task1 = $queue->take(.1);
        $task2 = $queue->touch($task1->getId(), 1);
        sleep(1);
        $task3 = $queue->take(.1);

        self::assertTaskInstance($task2);
        self::assertSame('touch_ttr_1', $task2->getData());
        self::assertEquals($task1, $task2);
        self::assertNull($task3);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('touch_ttl_1', {ttl = 1})
     */
    public function testTouchTimeToLive() : void
    {
        $queue = $this->getQueue();

        $task1 = $queue->take(.1);
        $task2 = $queue->touch($task1->getId(), 1);
        $queue->release($task1->getId());
        sleep(1);
        $task3 = $queue->take(.1);

        self::assertTaskInstance($task2);
        self::assertSame('touch_ttl_1', $task2->getData());
        self::assertEquals($task1, $task2);
        self::assertEquals($task2, $task3);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('touch_invalid_interval')
     */
    public function testTouchInvalidInterval() : void
    {
        $queue = $this->getQueue();
        $task = $queue->take(.1);

        foreach ([0, -1] as $interval) {
            self::assertNull($queue->touch($task->getId(), $interval));
        }
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('pri_low', {pri = 2})
     * @lua tube:put('pri_high', {pri = 1})
     */
    public function testPriority() : void
    {
        $queue = $this->getQueue();

        $task1 = $queue->take(.1);
        $queue->delete($task1->getId());

        $task2 = $queue->take(.1);
        $queue->delete($task2->getId());

        self::assertSame('pri_high', $task1->getData());
        self::assertSame('pri_low', $task2->getData());
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('delay_1', {delay = 1})
     */
    public function testDelay() : void
    {
        $queue = $this->getQueue();

        $task = $queue->peek(0);
        self::assertTask($task, 0, States::DELAYED, 'delay_1');

        sleep(1);

        $task = $queue->take(.1);
        self::assertTask($task, 0, States::TAKEN, 'delay_1');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('release_0')
     * @lua tube:take()
     */
    public function testDelayedRelease() : void
    {
        $queue = $this->getQueue();

        $queue->release(0, ['delay' => 1]);

        $task = $queue->peek(0);
        self::assertTask($task, 0, States::DELAYED, 'release_0');

        sleep(1);

        $task = $queue->take(.1);
        self::assertTask($task, 0, States::TAKEN, 'release_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('stat_delayed_0', {delay = 9999})
     * @lua tube:put('stat_delayed_1', {delay = 9999})
     */
    public function testStatsDelayed() : void
    {
        $count = $this->getQueue()->stats('tasks.delayed');

        self::assertSame(2, $count);
    }

    abstract protected function getQueue() : Queue;
}
