<?php

declare(strict_types=1);

namespace Kreait\Firebase\Tests\Integration\Messaging;

use Kreait\Firebase\Messaging\MessageToTopic;
use Kreait\Firebase\Tests\Integration\MessagingTestCase;

class MessageToTopicTest extends MessagingTestCase
{
    private $topic;

    protected function setUp()
    {
        parent::setUp();

        $this->topic = 'integration-test-topic';
        $this->fullMessageData['topic'] = $this->topic;
    }

    public function testSendEmptyMessage()
    {
        $this->assertSuccessfulMessage(MessageToTopic::create($this->topic));
    }
}
