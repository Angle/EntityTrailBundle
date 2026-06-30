<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Controller\Ajax;

use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Angle\EntityTrailBundle\Repository\EntityTrailLogRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * DataTables server-side data endpoint.
 */
final class EntityTrailLogController
{
    /**
     * Ordered list of column "data" keys, matched against DataTables' order request.
     *
     * @var list<string>
     */
    private const COLUMNS = ['entity', 'record', 'action', 'user', 'changes', 'created_at', 'actions'];

    public function __construct(
        private readonly EntityTrailLogRepository $repository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->request->get('draw', 1);
        $start = (int) $request->request->get('start', 0);
        $length = (int) $request->request->get('length', 25);

        /** @var array{value?: string} $searchParam */
        $searchParam = $request->request->all('search');
        $search = trim((string) ($searchParam['value'] ?? ''));

        [$sortCol, $sortDir] = $this->resolveOrder($request);

        $filterEntityType = $request->request->get('filterEntityType');
        $filterAction = $request->request->get('filterAction');
        $filterDateFrom = $request->request->get('filterDateFrom');
        $filterDateTo = $request->request->get('filterDateTo');

        $result = $this->repository->findForDataTable(
            $search,
            $start,
            $length,
            $sortCol,
            $sortDir,
            $filterEntityType !== null ? (string) $filterEntityType : null,
            $filterAction !== null ? (string) $filterAction : null,
            ($filterDateFrom !== null && $filterDateFrom !== '') ? (string) $filterDateFrom : null,
            ($filterDateTo !== null && $filterDateTo !== '') ? (string) $filterDateTo : null,
        );

        $data = [];
        foreach ($result['items'] as $log) {
            $data[] = $this->formatRow($log);
        }

        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data'            => $data,
        ]);
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolveOrder(Request $request): array
    {
        /** @var array<int, array{column?: string, dir?: string}> $order */
        $order = $request->request->all('order');
        $first = $order[0] ?? null;

        if ($first === null) {
            return ['created_at', 'DESC'];
        }

        $columnIndex = (int) ($first['column'] ?? 0);
        $dir = strtoupper((string) ($first['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sortCol = self::COLUMNS[$columnIndex] ?? 'created_at';

        return [$sortCol, $dir];
    }

    /**
     * @return array<string, string>
     */
    private function formatRow(EntityTrailLog $log): array
    {
        $viewUrl = $this->urlGenerator->generate('angle_entity_trail_view', ['code' => $log->getCode()]);
        $record = $log->getEntityCode() ?? (string) $log->getEntityId();
        $fields = implode(', ', array_keys($log->getChanges()));

        return [
            'entity'     => htmlspecialchars($log->getEntityShortName(), ENT_QUOTES),
            'record'     => htmlspecialchars($record, ENT_QUOTES),
            'action'     => $this->actionBadge($log->getAction()),
            'user'       => htmlspecialchars($log->getUserLabel() ?? '—', ENT_QUOTES),
            'changes'    => htmlspecialchars($fields, ENT_QUOTES),
            'created_at' => $log->getCreatedAt()->format('Y-m-d H:i'),
            'actions'    => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-primary">View</a>',
                htmlspecialchars($viewUrl, ENT_QUOTES),
            ),
        ];
    }

    private function actionBadge(string $action): string
    {
        $class = match ($action) {
            EntityTrailLog::ACTION_CREATE => 'bg-success',
            EntityTrailLog::ACTION_UPDATE => 'bg-warning text-dark',
            EntityTrailLog::ACTION_DELETE => 'bg-danger',
            default                 => 'bg-secondary',
        };

        return sprintf(
            '<span class="badge %s">%s</span>',
            $class,
            htmlspecialchars($action, ENT_QUOTES),
        );
    }
}
