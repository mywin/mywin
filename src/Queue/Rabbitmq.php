<?php
namespace mywin\Queue;
class Rabbitmq
{
	/**
	 * Connection instance
	 * @var AMQPConnection
	 */
	protected $_connection;

	/**
	 * Connection config
	 */
	protected $_config;

	/**
	 * Channel instance
	 *
	 * @var AMQPChannel
	 */
	protected $_channel;

	/**
	 * Exchange instance
	 * @var AMQPExchange
	 */
	protected $_exchange;

	/**
	 * Queue instance
	 * @var AMQPQueue
	 */
	protected $_queue;

	/**
	 * Exchange Name
	 * @var string
	 */
	protected $_publishParams = [];

	/**
	 * queue param
	 * @var array
	 */
	protected $_params = [
		'queue' => 'S_QUEUE',
		'router' => 'S_ROUTER',
		'exchange' => 'S_EXCHANGE'
	];
	/**
	 * Logger
	 * @var Zend_Log
	 */
	protected $_logger;

	public function __construct($config) {
		if (!extension_loaded('amqp')) {
			throw new \Exception('amqp extension is not load!');
		}
		$this->_config = $config;
	}

	/**
	 * Connect to server
	 * @return AMQPConnection
	 */
	protected function _getConnection()
	{
		if (!$this->_connection) {
			$this->_connection = new \AMQPConnection($this->_config);
			if (!$this->_connection->connect()) {
				throw new \Exception("Cannot connect to the broker \n");
			}
		}
		return $this->_connection;
	}

	/**
	 * Get transport channel for current connection.
	 *
	 * @return AMQPChannel
	 */
	protected function _getChannel()
	{
		if (!$this->_channel) {
			$this->_channel = new \AMQPChannel($this->_getConnection());
		}
		return $this->_channel;
	}

	/**
	 * Get transport exchange for current channel.
	 * @return AMQPExchange
	 */
	protected function _getExchange()
	{
		if (!$this->_exchange) {
			$this->_exchange = new \AMQPExchange($this->_getChannel());
			//设置exchange名称
			$this->_exchange->setName($this->_params['exchange']);
			//设置exchange类型
			$this->_exchange->setType(AMQP_EX_TYPE_DIRECT);
			//设置exchange参数
			$this->_exchange->setFlags(AMQP_DURABLE | AMQP_AUTODELETE);
		}
		return $this->_exchange;
	}

	/**
	 * Get transport queue for current channel.
	 * @return AMQPQueue
	 */
	protected function _getQueue()
	{
		if (!$this->_queue) {
			//创建队列
			$this->_queue = new \AMQPQueue($this->_getChannel());
			//设置队列名字 如果不存在则添加
			$this->_queue->setName($this->_params['queue']);
			$this->_queue->setFlags(AMQP_DURABLE | AMQP_AUTODELETE);
			$this->_queue->declareQueue();
			//将你的队列绑定到routingKey
			$this->_queue->bind($this->_params['exchange'], $this->_params['router']);
		}
		return $this->_queue;
	}

	/**
	 * Set logger
	 * @param Zend_Log $logger Logger
	 * @return Oggetto_Messenger_Model_Transport_Rabbitmq
	 */
	public function setLogger(Zend_Log $logger)
	{
		$this->_logger = $logger;
		return $this;
	}

	/**
	 * Start receiving messages countinously
	 *
	 * @param Oggetto_Messenger_Model_Message_Interface $messagePrototype Messages class to be received
	 * @param array|Closure                             $callback         Callback to be called on message receive
	 *
	 * @throws Exception
	 * @return void
	 */
	public function startReceiving(Oggetto_Messenger_Model_Message_Interface $messagePrototype, $callback)
	{
		try {
			$this->_getChannel()->basic_qos(null, 1, null);
			foreach ($this->_consumeQueues as $_queue) {
				$this->_getChannel()->queue_declare($_queue, false, true, false, false);
				$transport = $this;
				$this->_getChannel()->basic_consume($_queue, '', false, false, false, false,
					function ($msg) use ($transport, $callback, $messagePrototype) {
						$transport->receiveMessage($msg, $messagePrototype, $callback);
					}
				);
			}

			while (count($this->_getChannel()->callbacks)) {
				$this->_getChannel()->wait();
			}
		} catch (Exception $e) {
			$this->_close();
			throw $e;
		}
		$this->_close();
	}

	/**
	 * Receive message
	 * @param queue
	 * @param $callback
	 * @param array|Closure                             $callback         Callback
	 * @throws Exception
	 * @return void

	public function receive($queue, $callback) {
		try {
			$this->_logger->info("Received new message: {$rabbitMessage->body}");

			$message = clone $messagePrototype;
			$message->init($rabbitMessage->body);
			call_user_func_array($callback, array($message));
		} catch (Oggetto_Messenger_Exception_Critical $e) {
			// Only critical exceptions are supposed to stop the receiver
			throw $e;
		} catch (Exception $e) {
			$this->_logger->err($e);
		}

		$channel = $rabbitMessage->{'delivery_info'}['channel'];
		$channel->basic_ack($rabbitMessage->{'delivery_info'}['delivery_tag']);
	}
	 */

	public function receive($queue) {
		$this->_params['queue'] = $queue;
		//将你的队列绑定到routingKey
		//连接RabbitMQ
		$queue = $this->_getQueue();
		//消息获取
		
		$data = $queue->get(AMQP_AUTOACK);
		$this->close();
		return $data;
	}

	/**
	 * Close session
	 * @return boolean
	 */
	public function close() {
		return $this->_getConnection()->disconnect();
	}

	/**
	 * Send data
	 * @param array $data
	 * @return boolean
	 */
	public function send($data)	{
		$router = $this->_params['router'];
		if(is_array($data)) {
			$data = serialize($data);
		}
		if (!$router) {
			$this->_logger->warn('Publish router is not defined: message cannot be sent');
			return;
		}
		//创建channel
		$channel = $this->_getChannel();

		//创建exchange
		$exchange = $this->_getExchange();
		if(!$exchange->declareExchange()) {
			return false;
		}
		//创建队列
		$this->_getQueue();
		$publishParams = [
			'content_type' => 'text/plain',
			'app_id' => 100,
			'message_id' => 100,
			'delivery_mode' => 2,
			'priority' => 9,
			'type' => 'system',
			'timestamp' => time(),
			'reply_to' => 'hacker',
			'headers' => ['name' => 'Musikar']
		];
		$publishParams = array_merge($this->_publishParams, $publishParams);

		$channel->startTransaction();
		//将你的消息通过制定routingKey发送
		$result = $exchange->publish(
			$data,
			$router,
			AMQP_NOPARAM,
			$publishParams
		);
		$result = $exchange->publish(
			$data,
			$router,
			AMQP_NOPARAM,
			$publishParams
		);
		$result = $exchange->publish(
			$data,
			$router,
			AMQP_NOPARAM,
			$publishParams
		);
		$channel->commitTransaction();
		$this->close();
		return $result;
	}

	public function setParams($params = []) {
		$this->_params = array_merge($this->_params, $params);
		return $this;
	}
	/**
	 * Set publish param
	 * @param array $publishParam
	 * @return this
	 */
	public function setPublishParams($publishParams = []) {
		$this->_publishParams = $publishParams;
		return $this;
	}

	public function __destruct() {
		$this->close();
	}
}
