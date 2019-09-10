<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Client\RabbitMq\Model\Connection\ConnectionBuilder;

use Generated\Shared\Transfer\QueueConnectionTransfer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Spryker\Client\RabbitMq\Dependency\Client\RabbitMqToStoreClientInterface;
use Spryker\Client\RabbitMq\Model\Connection\Connection;
use Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface;
use Spryker\Client\RabbitMq\Model\Helper\QueueEstablishmentHelperInterface;
use Spryker\Client\RabbitMq\RabbitMqConfig;

class ConnectionBuilder implements ConnectionBuilderInterface
{
    /**
     * @var \Spryker\Client\RabbitMq\RabbitMqConfig
     */
    protected $config;

    /**
     * @var \Spryker\Client\RabbitMq\Dependency\Client\RabbitMqToStoreClientInterface
     */
    protected $storeClient;

    /**
     * @var \Spryker\Client\RabbitMq\Model\Helper\QueueEstablishmentHelperInterface
     */
    protected $queueEstablishmentHelper;

    /**
     * @var \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface[]
     */
    protected $createdConnectionsByConnectionName;

    /**
     * @param \Spryker\Client\RabbitMq\RabbitMqConfig $config
     * @param \Spryker\Client\RabbitMq\Dependency\Client\RabbitMqToStoreClientInterface $storeClient
     * @param \Spryker\Client\RabbitMq\Model\Helper\QueueEstablishmentHelperInterface $queueEstablishmentHelper
     */
    public function __construct(
        RabbitMqConfig $config,
        RabbitMqToStoreClientInterface $storeClient,
        QueueEstablishmentHelperInterface $queueEstablishmentHelper
    ) {
        $this->config = $config;
        $this->storeClient = $storeClient;
        $this->queueEstablishmentHelper = $queueEstablishmentHelper;
    }

    /**
     * @param \Generated\Shared\Transfer\QueueConnectionTransfer $queueConnectionTransfer
     *
     * @return \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface
     */
    public function createConnectionByQueueConnectionTransfer(QueueConnectionTransfer $queueConnectionTransfer): ConnectionInterface
    {
        return $this->createOrGetConnection($queueConnectionTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\QueueConnectionTransfer $queueConnectionTransfer
     *
     * @return \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface
     */
    protected function createOrGetConnection(QueueConnectionTransfer $queueConnectionTransfer): ConnectionInterface
    {
        if (isset($this->createdConnectionsByConnectionName[$queueConnectionTransfer->getName()])) {
            return $this->createdConnectionsByConnectionName[$queueConnectionTransfer->getName()];
        }

        $connection = $this->createConnection($queueConnectionTransfer);
        $this->createdConnectionsByConnectionName[$queueConnectionTransfer->getName()] = $connection;

        return $connection;
    }

    /**
     * @param \Generated\Shared\Transfer\QueueConnectionTransfer $queueConnectionTransfer
     *
     * @return \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface
     */
    protected function createConnection(QueueConnectionTransfer $queueConnectionTransfer): ConnectionInterface
    {
        return new Connection(
            $this->createAmqpStreamConnection($queueConnectionTransfer),
            $this->queueEstablishmentHelper,
            $queueConnectionTransfer
        );
    }

    /**
     * @param \Generated\Shared\Transfer\QueueConnectionTransfer $queueConnectionTransfer
     *
     * @return \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected function createAmqpStreamConnection(QueueConnectionTransfer $queueConnectionTransfer): AMQPStreamConnection
    {
        $defaultQueueConnectionTransfer = $this->config->getDefaultQueueConnectionConfig();

        return new AMQPStreamConnection(
            $queueConnectionTransfer->getHost(),
            $queueConnectionTransfer->getPort(),
            $queueConnectionTransfer->getUsername(),
            $queueConnectionTransfer->getPassword(),
            $queueConnectionTransfer->getVirtualHost(),
            $queueConnectionTransfer->getInsist() ?? $defaultQueueConnectionTransfer->getInsist(),
            $queueConnectionTransfer->getLoginMethod() ?? $defaultQueueConnectionTransfer->getLoginMethod(),
            $queueConnectionTransfer->getLoginResponse(),
            $queueConnectionTransfer->getLocale() ?? $this->getDefaultLocale(),
            $queueConnectionTransfer->getConnectionTimeout() ?? $defaultQueueConnectionTransfer->getConnectionTimeout(),
            $queueConnectionTransfer->getReadWriteTimeout() ?? $defaultQueueConnectionTransfer->getReadWriteTimeout(),
            null,
            $queueConnectionTransfer->getKeepAlive() ?? $defaultQueueConnectionTransfer->getKeepAlive(),
            $queueConnectionTransfer->getHeartBeat() ?? $defaultQueueConnectionTransfer->getHeartBeat(),
            $queueConnectionTransfer->getChannelRpcTimeout() ?? $defaultQueueConnectionTransfer->getChannelRpcTimeout(),
            $queueConnectionTransfer->getSslProtocol()
        );
    }

    /**
     * @return string
     */
    protected function getDefaultLocale(): string
    {
        return current($this->storeClient->getCurrentStore()->getAvailableLocaleIsoCodes());
    }

    /**
     * @param \Generated\Shared\Transfer\QueueConnectionTransfer[] $queueConnectionTransfers
     *
     * @return \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface[]
     */
    public function createConnectionsByQueueConnectionTransfers(array $queueConnectionTransfers): array
    {
        $connections = [];

        foreach ($queueConnectionTransfers as $queueConnectionTransfer) {
            $connection = $this->createOrGetConnection($queueConnectionTransfer);
            $uniqueChannelId = $this->getUniqueChannelId($connection);
            if (!isset($connections[$uniqueChannelId])) {
                $connections[$uniqueChannelId] = $connection;
            }
        }

        return $connections;
    }

    /**
     * @param \Spryker\Client\RabbitMq\Model\Connection\ConnectionInterface $connection
     *
     * @return string
     */
    protected function getUniqueChannelId(ConnectionInterface $connection): string
    {
        return sprintf('%s-%s', $connection->getVirtualHost(), $connection->getChannel()->getChannelId());
    }
}