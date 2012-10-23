<?php

namespace Promise;

class Deferred implements PromiseInterface, ResolverInterface
{
    private $completed;

    private $promise;

    private $resolver;

    private $handlers = array();

    private $progressHandlers = array();

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        if (null !== $this->completed) {
            return $this->completed->then($fulfilledHandler, $errorHandler, $progressHandler);
        }

        $deferred = new self();

        if ($progressHandler) {
            $progHandler = function ($update) use ($deferred, $progressHandler) {
                try {
                    $deferred->progress(call_user_func($progressHandler, $update));
                } catch (\Exception $e) {
                    $deferred->progress($e);
                }
            };
        } else {
            $progHandler = array($deferred, 'progress');
        }

        $this->handlers[] = function ($promise) use ($fulfilledHandler, $errorHandler, $deferred, $progHandler) {
            $promise
                ->then($fulfilledHandler, $errorHandler)
                ->then(
                    array($deferred, 'resolve'),
                    array($deferred, 'reject'),
                    $progHandler
                );
        };

        $this->progressHandlers[] = $progHandler;

        return $deferred->promise();
    }

    public function resolve($result = null)
    {
        if (null !== $this->completed) {
            return Util::resolve($result);
        }

        $this->completed = Util::resolve($result);

        foreach ($this->handlers as $handler) {
            call_user_func($handler, $this->completed);
        }

        $this->progressHandlers = $this->handlers = array();

        return $this->completed;
    }

    public function reject($error = null)
    {
        return $this->resolve(new RejectedPromise($error));
    }

    public function progress($update = null)
    {
        if (null !== $this->completed) {
            return;
        }

        foreach ($this->progressHandlers as $handler) {
            call_user_func($handler, $update);
        }
    }

    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new DeferredPromise($this);
        }

        return $this->promise;
    }

    public function resolver()
    {
        if (null === $this->resolver) {
            $this->resolver = new DeferredResolver($this);
        }

        return $this->resolver;
    }
}
