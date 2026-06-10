<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Controller;

use Angle\TrailBundle\Repository\TrailLogRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Admin web controller — list and view pages. The actual table rows are loaded
 * server-side via the Ajax controller.
 */
final class TrailLogController
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly TrailLogRepository $repository,
        private readonly array $config,
    ) {
    }

    public function list(): Response
    {
        return new Response($this->twig->render('@AngleTrail/trail_log/list.html.twig', [
            'entity_types'        => $this->repository->findDistinctEntityTypes(),
            'admin_route_prefix'  => $this->config['admin_route_prefix'] ?? '',
        ]));
    }

    public function view(string $code): Response
    {
        $log = $this->repository->findOneBy(['code' => $code]);

        if ($log === null) {
            throw new NotFoundHttpException(sprintf('Trail log "%s" not found.', $code));
        }

        return new Response($this->twig->render('@AngleTrail/trail_log/view.html.twig', [
            'log' => $log,
        ]));
    }
}
