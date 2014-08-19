<?php


namespace Kumar\SesMailer;

use Aws\Ses\SesClient;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Log\Writer;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\SerializableClosure;
use Illuminate\View\Factory;

/**
 * Class SesMailer
 *
 * Few methods are Extracted from Taylor Otwell's Illuminate\Mail\Mailer
 *
 * @package Kumar\Sesmailer
 */
class Mailer {

    /**
     * @var \Illuminate\View\Factory
     */
    private $view;
    /**
     * @var \Aws\Common\Facade\Ses
     */
    private $sesClient;

    /**
     * The global from address and name.
     *
     * @var array
     */
    protected $from;
    /**
     * @var \Illuminate\Events\Dispatcher
     */
    private $events;

    /**
     * The log writer instance.
     *
     * @var \Illuminate\Log\Writer
     */
    protected $logger;

    /**
     * The IoC container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * The QueueManager instance.
     *
     * @var \Illuminate\Queue\QueueManager
     */
    protected $queue;

    /**
     * Indicates if the actual sending is disabled.
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * @param Factory                       $view
     * @param \Illuminate\Events\Dispatcher $events
     *
     * @param \Aws\Ses\SesClient            $sesClient
     *
     */
    function __construct(Factory $view ,Dispatcher $events,SesClient $sesClient ){
        $this->view = $view;
        $this->events = $events;
        $this->sesClient = $sesClient;
    }

    /**
     * Send a new message using a view.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return response
     */
    public function send($view, array $data, $callback)
    {
        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        list($view, $plain) = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        $this->callMessageBuilder($callback, $message);

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        $this->addContent($message, $view, $plain, $data);


        return $this->sendMessage($message);
    }

    /**
     * Queue a new e-mail message for sending.
     *
     * @param  string|array  $view
     * @param  array   $data
     * @param  \Closure|string  $callback
     * @param  string  $queue
     * @return void
     */
    public function queue($view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        $this->queue->push('Kumar\SesMailer\Mailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * @param  string  $queue
     * @param  string|array  $view
     * @param  array   $data
     * @param  \Closure|string  $callback
     * @return void
     */
    public function queueOn($queue, $view, array $data, $callback)
    {
        $this->queue($view, $data, $callback, $queue);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param  int  $delay
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @param  string  $queue
     * @return void
     */
    public function later($delay, $view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        $this->queue->later($delay, 'sesmailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds on the given queue.
     *
     * @param  string  $queue
     * @param  int  $delay
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return void
     */
    public function laterOn($queue, $delay, $view, array $data, $callback)
    {
        $this->later($delay, $view, $data, $callback, $queue);
    }

    /**
     * Build the callable for a queued e-mail job.
     *
     * @param  mixed  $callback
     * @return mixed
     */
    protected function buildQueueCallable($callback)
    {
        if ( ! $callback instanceof \Closure) return $callback;

        return \serialize(new SerializableClosure($callback));
    }

    /**
     * Handle a queued e-mail message job.
     *
     * @param  \Illuminate\Queue\Jobs\Job  $job
     * @param  array  $data
     * @return void
     */
    public function handleQueuedMessage($job, $data)
    {
        $this->send($data['view'], $data['data'], $this->getQueuedCallable($data));

        $job->delete();
    }

    /**
     * Get the true callable for a queued e-mail message.
     *
     * @param  array  $data
     * @return mixed
     */
    protected function getQueuedCallable(array $data)
    {
        if (str_contains($data['callback'], 'SerializableClosure'))
        {
            return with(unserialize($data['callback']))->getClosure();
        }

        return $data['callback'];
    }

    /**
     * Call the provided message builder.
     *
     * @param  \Closure|string  $callback
     * @param  \Illuminate\Mail\Message  $message
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function callMessageBuilder($callback, $message)
    {
        if ($callback instanceof \Closure)
        {
            return call_user_func($callback, $message);
        }
        elseif (is_string($callback))
        {
            return $this->container[$callback]->mail($message);
        }

        throw new \InvalidArgumentException("Callback is not valid.");
    }
    /**
     * Parse the given view name or array.
     *
     * Extracted from Taylor Otwell mailer code
     *
     * @param  string|array  $view
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseView($view)
    {
        if (is_string($view)) return array($view, null);

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since must should contain both views with numeric keys.
        if (is_array($view) && isset($view[0]))
        {
            return $view;
        }

        // If the view is an array, but doesn't contain numeric keys, we will assume
        // the the views are being explicitly specified and will extract them via
        // named keys instead, allowing the developers to use one or the other.
        elseif (is_array($view))
        {
            return array(
                array_get($view, 'html'), array_get($view, 'text')
            );
        }

        throw new \InvalidArgumentException("Invalid view.");
    }


    /**
     * Create a new message instance.
     *
     * @return \Kumar\SesMailer\SesMessage
     */
    protected function createMessage()
    {
        $message = new SesMessage();

        // If a global from address has been specified we will set it on every message
        // instances so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push the address.
        if (isset($this->from['address']))
        {
            $message->from($this->from['address'], $this->from['name']);
        }

        return $message;
    }

    /**
     * Add the content to a given message.
     *
     * @param \Kumar\Sesmailer\SesMessage  $message
     * @param  string  $view
     * @param  string  $plain
     * @param  array   $data
     * @return void
     */
    protected function addContent(SesMessage $message, $view, $plain, $data)
    {
        if (isset($view))
        {
            $message->html($this->getView($view, $data));
        }

        if (isset($plain))
        {
            $message->text($this->getView($plain, $data));
        }
    }

    /**
     * Render the given view.
     *
     * @param  string  $view
     * @param  array   $data
     * @return \Illuminate\View\View
     */
    protected function getView($view, $data)
    {
        return $this->view->make($view, $data)->render();
    }

    /**
     * @param SesMessage $message
     *
     * @return array|\Guzzle\Service\Resource\Model
     */
    private function sendMessage(SesMessage $message)
    {
        if ($this->events)
        {
            $this->events->fire('mailer.sending', array($message));
        }
        $response = [];

        if ( ! $this->pretending)
        {
           $response =  $this->sesClient->sendEmail($message->getMessage());
        }
        elseif (isset($this->logger))
        {
            $this->logMessage($message);
        }

        if ($this->events)
        {
            $this->events->fire('mailer.sent', ["messsage" => $message, "response" =>$response]);
        }

        return $response;
    }


    /**
     * Log that a message was sent.
     *
     * @param  SesMessage  $message
     * @return void
     */
    protected function logMessage($message)
    {
        $emails = implode(', ', $message->getTo());

        $this->logger->info("Pretending to mail message to: {$emails}");
    }

    /**
     * Set the global from address and name.
     *
     * @param  string  $address
     * @param  string  $name
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Set the log writer instance.
     *
     * @param  \Illuminate\Log\Writer  $logger
     * @return $this
     */
    public function setLogger(Writer $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Set the queue manager instance.
     *
     * @param  \Illuminate\Queue\QueueManager  $queue
     * @return $this
     */
    public function setQueue(QueueManager $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Tell the mailer to not really send messages.
     *
     * @param  bool  $value
     * @return void
     */
    public function pretend($value = true)
    {
        $this->pretending = $value;
    }

} 