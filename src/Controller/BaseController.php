<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends AbstractController
{
    private float $requestStart;

    public function __construct()
    {
        $this->requestStart = microtime(true);
    }

    protected function response(
        bool $success = true,
        array $options = [],
        string $returnType = 'json',
        int $status = Response::HTTP_OK
    ): Response {
        $template = $options['template'] ?? null;
        $templateData = $this->withDefaultTemplateData($options['templateData'] ?? []);

        if ($returnType === 'html') {
            return $this->render($template, $templateData, new Response('', $status));
        }

        $content = $options['content'] ?? '';
        if ($template !== null) {
            $content = $this->renderView($template, $templateData);
        }

        return new JsonResponse([
            'success' => $success,
            'runtime' => $this->getRuntime(),
            'content' => $content,
            'data' => $options['data'] ?? [],
        ], $status);
    }

    protected function getRuntime(): float
    {
        return round((microtime(true) - $this->requestStart) * 1000, 2);
    }

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return parent::render($view, $this->withDefaultTemplateData($parameters), $response);
    }

    protected function renderView(string $view, array $parameters = []): string
    {
        return parent::renderView($view, $this->withDefaultTemplateData($parameters));
    }

    protected function debug(array $parameters): array
    {
        echo "<pre>";
        print_r($parameters);
        echo "</pre>";
    }
    private function withDefaultTemplateData(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $permission = $user->getPermission();

            $data['user'] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUserName(),
                'phone' => $user->getPhone(),
                'image' => $user->getImage(),
                'permission' => $permission ? [
                    'id' => $permission->getId(),
                    'title' => $permission->getTitle(),
                    'code' => $permission->getCode(),
                ] : null,
            ];
        }

        $parameters['data'] = $data;

        return $parameters;
    }
}
