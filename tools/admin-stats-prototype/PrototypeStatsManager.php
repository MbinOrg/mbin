<?php

declare(strict_types=1);

// Experimental reader, enabled only by this directory's local router.
final class PrototypeStatsManager extends App\Service\InstanceStatsManager
{
    public function __construct(private Doctrine\DBAL\Connection $db)
    {
    }

    public function count(?string $period = null, bool $withFederated = false): array
    {
        $date = $period ? new DateTimeImmutable($period) : null;
        $sql = 'SELECT kind,SUM(cnt)::bigint AS cnt FROM admin_perf.'.($date ? 'totals' : 'all_time').' WHERE '.($withFederated ? 'true' : 'is_local');
        $params = [];
        if ($date) {
            $sql .= ' AND bucket > CAST(:date AS date)';
            $params['date'] = $date->format('Y-m-d H:i:s');
        }
        $counts = [];
        foreach ($this->db->fetchAllAssociative($sql.' GROUP BY kind', $params) as $row) {
            $counts[$row['kind']] = (int) $row['cnt'];
        }
        // Preserve the original strict rolling timestamp cutoff, not calendar days.
        // Full days use totals; only the boundary day's rows need examination.
        if ($date) {
            $params['end'] = $date->modify('+1 day')->setTime(0, 0)->format('Y-m-d H:i:s');
            foreach (['user', 'magazine', 'entry', 'entry_comment', 'post', 'post_comment', 'entry_vote', 'entry_comment_vote', 'post_vote', 'post_comment_vote', 'favourite'] as $table) {
                $sql = 'SELECT COUNT(*) FROM public."'.$table.'" e';
                if (!\in_array($table, ['user', 'magazine'], true)) {
                    $sql .= ' INNER JOIN public."user" u ON u.id=e.user_id';
                }
                $sql .= ' WHERE e.created_at > :date AND e.created_at < :end';
                if ('magazine' !== $table) {
                    $sql .= \in_array($table, ['user'], true) ? ' AND NOT e.is_deleted' : ' AND NOT u.is_deleted';
                }
                if (!$withFederated) {
                    $sql .= \in_array($table, ['entry_vote', 'entry_comment_vote', 'post_vote', 'post_comment_vote', 'favourite'], true) ? ' AND u.ap_id IS NULL' : ' AND e.ap_id IS NULL';
                }
                $counts[$table] = ($counts[$table] ?? 0) + (int) $this->db->fetchOne($sql, $params);
            }
        }

        return [
            'users' => $counts['user'] ?? 0,
            'magazines' => $counts['magazine'] ?? 0,
            'entries' => $counts['entry'] ?? 0,
            'comments' => $counts['entry_comment'] ?? 0,
            'posts' => ($counts['post'] ?? 0) + ($counts['post_comment'] ?? 0),
            'votes' => array_sum(array_map(fn ($kind) => $counts[$kind] ?? 0, ['entry_vote', 'entry_comment_vote', 'post_vote', 'post_comment_vote', 'favourite'])),
        ];
    }
}
