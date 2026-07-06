<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Controller;

use Angle\EntityTrailBundle\Repository\EntityTrailLogRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Admin web controller — list and view pages. The actual table rows are loaded
 * server-side via the Ajax controller.
 */
final class EntityTrailLogController
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTrailLogRepository $repository,
        private readonly array $config,
    ) {
    }

    public function list(): Response
    {
        return new Response($this->twig->render('@AngleEntityTrail/entity_trail_log/list.html.twig', [
            'entity_types'        => $this->repository->findDistinctEntityTypes(),
            'users'               => $this->repository->findDistinctUsers(),
            'admin_route_prefix'  => $this->config['admin_route_prefix'] ?? '',
        ]));
    }

    public function view(string $code): Response
    {
        $log = $this->repository->findOneBy(['code' => $code]);

        if ($log === null) {
            throw new NotFoundHttpException(sprintf('EntityTrail log "%s" not found.', $code));
        }

        return new Response($this->twig->render('@AngleEntityTrail/entity_trail_log/view.html.twig', [
            'log' => $log,
        ]));
    }
}
