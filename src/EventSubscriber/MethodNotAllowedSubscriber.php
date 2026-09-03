<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class MethodNotAllowedSubscriber
{
    public function __construct(private readonly Environment $twig)
    {
    }

    #[AsEventListener(event: 'kernel.exception')]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof NotFoundHttpException) {
            $template = 'errors/error404.html.twig';
            $statusCode = Response::HTTP_NOT_FOUND;
        } elseif ($exception instanceof MethodNotAllowedHttpException) {
            $template = 'errors/error405.html.twig';
            $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
        } else {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render($template),
            $statusCode,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ));
    }
}
