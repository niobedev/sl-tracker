<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * Session pairing strategy: use LEAD() window function to find the next event per avatar.
 * A session = a 'login' row whose immediate next row (per avatar, ordered by time) is a 'logout'.
 * This is O(n log n) vs the correlated-subquery O(n²) approach.
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Leaderboard: total minutes spent per avatar, sorted descending.
     *
     * @return array<array{avatar_key:string, display_name:string, username:string, total_minutes:float, visit_count:int}>
     */
    public function getLeaderboard(int $limit = 25): array
    {
        $sql = <<<SQL
            WITH paired AS (
                SELECT
                    avatar_key, display_name, username, action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action,
                    ROW_NUMBER()   OVER (PARTITION BY avatar_key ORDER BY event_ts DESC) AS rn
                FROM event
            )
            SELECT
                avatar_key,
                MAX(CASE WHEN rn = 1 THEN display_name END) AS display_name,
                MAX(CASE WHEN rn = 1 THEN username END) AS username,
                COUNT(CASE WHEN action = 'login' AND next_action = 'logout' THEN 1 END) AS visit_count,
                SUM(CASE WHEN action = 'login' AND next_action = 'logout'
                    THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS total_minutes
            FROM paired
            GROUP BY avatar_key
            HAVING visit_count > 0
            ORDER BY total_minutes DESC
            LIMIT $limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Online avatars: avatars whose most recent event is a 'login' (no subsequent logout).
     *
     * @return array<array{avatar_key:string, display_name:string, joined_at:int}>
     */
    public function getLiveVisitors(): array
    {
        $sql = <<<SQL
            WITH latest AS (
                SELECT
                    avatar_key, display_name, action, event_ts,
                    ROW_NUMBER() OVER (PARTITION BY avatar_key ORDER BY event_ts DESC) AS rn
                FROM event
            )
            SELECT avatar_key, display_name, UNIX_TIMESTAMP(event_ts) AS joined_at
            FROM latest
            WHERE rn = 1 AND action = 'login'
            ORDER BY event_ts DESC
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Avatars with activity in a given period with session count and total time.
     *
     * @param string $period today|yesterday|week|month|year|all
     * @return array<array{avatar_key:string,display_name:string,visit_count:int,total_minutes:float,last_login:int}>
     */
    public function getRecentVisitors(string $period = 'today'): array
    {
        [$from, $until] = match($period) {
            'yesterday' => ['CURDATE() - INTERVAL 1 DAY',                  'CURDATE()'],
            'week'      => ['CURDATE() - INTERVAL 6 DAY',                  'CURDATE() + INTERVAL 1 DAY'],
            'month'     => ['CURDATE() - INTERVAL 29 DAY',                 'CURDATE() + INTERVAL 1 DAY'],
            'year'      => ['DATE(CONCAT(YEAR(CURDATE()),\'-01-01\'))',     'CURDATE() + INTERVAL 1 DAY'],
            'all'       => [null,                                           null],
            default     => ['CURDATE()',                                    'CURDATE() + INTERVAL 1 DAY'],
        };

        $whereClause = match(true) {
            $from !== null && $until !== null => "AND e.event_ts >= $from AND e.event_ts < $until",
            default => '',
        };

        $sql = <<<SQL
            WITH latest_names AS (
                SELECT avatar_key,
                    FIRST_VALUE(display_name) OVER (PARTITION BY avatar_key ORDER BY event_ts DESC) AS display_name
                FROM event
            ),
            paired AS (
                SELECT
                    avatar_key, action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action
                FROM event e
                WHERE 1=1 $whereClause
            ),
            period_stats AS (
                SELECT
                    avatar_key,
                    COUNT(CASE WHEN action = 'login' AND next_action = 'logout' THEN 1 END) AS visit_count,
                    SUM(CASE WHEN action = 'login' AND next_action = 'logout'
                        THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS total_minutes,
                    MAX(CASE WHEN action = 'login' THEN UNIX_TIMESTAMP(event_ts) END) AS last_login
                FROM paired
                GROUP BY avatar_key
            )
            SELECT p.avatar_key, ln.display_name, p.visit_count, p.total_minutes, p.last_login
            FROM period_stats p
            JOIN (SELECT DISTINCT avatar_key, display_name FROM latest_names) ln USING (avatar_key)
            WHERE p.last_login IS NOT NULL
            ORDER BY p.last_login DESC
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Activity heatmap: count of login events per weekday (0=Sun…6=Sat) and hour (0-23).
     *
     * @return array<array{weekday:int, hour:int, count:int}>
     */
    public function getHeatmap(): array
    {
        $sql = <<<SQL
            SELECT
                DAYOFWEEK(event_ts) - 1 AS weekday,
                HOUR(event_ts) AS hour,
                COUNT(*) AS count
            FROM event
            WHERE action = 'login'
            GROUP BY weekday, hour
            ORDER BY weekday, hour
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Hourly histogram: login events per hour of day.
     *
     * @return array<array{hour:int, count:int}>
     */
    public function getHourlyHistogram(): array
    {
        $sql = <<<SQL
            SELECT HOUR(event_ts) AS hour, COUNT(*) AS count
            FROM event
            WHERE action = 'login'
            GROUP BY hour
            ORDER BY hour
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Daily avatar counts: unique avatars and total logins per calendar day.
     *
     * @return array<array{day:string, visitors:int, visits:int}>
     */
    public function getDailyStats(): array
    {
        $sql = <<<SQL
            SELECT
                DATE(event_ts) AS day,
                COUNT(DISTINCT avatar_key) AS visitors,
                COUNT(*) AS visits
            FROM event
            WHERE action = 'login'
            GROUP BY day
            ORDER BY day
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Day-of-week popularity.
     *
     * @return array<array{weekday:int, weekday_name:string, visitors:int, visits:int}>
     */
    public function getDayOfWeekStats(): array
    {
        $sql = <<<SQL
            SELECT
                DAYOFWEEK(event_ts) - 1 AS weekday,
                DAYNAME(event_ts) AS weekday_name,
                COUNT(DISTINCT avatar_key) AS visitors,
                COUNT(*) AS visits
            FROM event
            WHERE action = 'login'
            GROUP BY weekday, weekday_name
            ORDER BY weekday
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Concurrent presence: number of avatars present at each event moment (last N days).
     *
     * @return array<array{ts:string, concurrent:int}>
     */
    public function getConcurrentPresence(int $days = 90): array
    {
        // Build a timeline of +1 (login) and -1 (logout) events, then running sum
        $sql = <<<SQL
            SELECT
                event_ts AS ts,
                SUM(delta) OVER (ORDER BY event_ts ROWS UNBOUNDED PRECEDING) AS concurrent
            FROM (
                SELECT event_ts, CASE WHEN action = 'login' THEN 1 ELSE -1 END AS delta
                FROM event
                WHERE event_ts >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ) deltas
            ORDER BY event_ts
            LIMIT 2000
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, ['days' => $days]);
    }

    /**
     * Per-avatar stats summary.
     *
     * @return array{avatar_key:string,display_name:string,username:string,visit_count:int,total_minutes:float,avg_minutes:float,first_login:string,last_login:string}|null
     */
    public function getAvatarStats(string $avatarKey): ?array
    {
        $sql = <<<SQL
            WITH paired AS (
                SELECT
                    avatar_key, display_name, username, action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action,
                    ROW_NUMBER()   OVER (PARTITION BY avatar_key ORDER BY event_ts DESC) AS rn
                FROM event
                WHERE avatar_key = :key
            )
            SELECT
                avatar_key,
                MAX(CASE WHEN rn = 1 THEN display_name END) AS display_name,
                MAX(CASE WHEN rn = 1 THEN username END) AS username,
                COUNT(CASE WHEN action = 'login' AND next_action = 'logout' THEN 1 END) AS visit_count,
                SUM(CASE WHEN action = 'login' AND next_action = 'logout'
                    THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS total_minutes,
                AVG(CASE WHEN action = 'login' AND next_action = 'logout'
                    THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS avg_minutes,
                MIN(event_ts) AS first_visit,
                MAX(event_ts) AS last_visit
            FROM paired
            GROUP BY avatar_key
        SQL;

        $row = $this->getEntityManager()
            ->getConnection()
            ->fetchAssociative($sql, ['key' => $avatarKey]);

        return $row ?: null;
    }

    /**
     * Per-avatar hourly histogram.
     *
     * @return array<array{hour:int, count:int}>
     */
    public function getAvatarHourlyHistogram(string $avatarKey): array
    {
        $sql = <<<SQL
            SELECT HOUR(event_ts) AS hour, COUNT(*) AS count
            FROM event
            WHERE action = 'login' AND avatar_key = :key
            GROUP BY hour
            ORDER BY hour
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, ['key' => $avatarKey]);
    }

    /**
     * Per-avatar session history (recent completed sessions).
     *
     * @return array<array{logged_in_at:int, logged_out_at:int, duration_minutes:float}>
     */
    public function getAvatarVisitHistory(string $avatarKey, int $limit = 50): array
    {
        $sql = <<<SQL
            WITH paired AS (
                SELECT
                    action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action
                FROM event
                WHERE avatar_key = :key
            )
            SELECT
                UNIX_TIMESTAMP(event_ts) AS joined_at,
                UNIX_TIMESTAMP(next_ts)  AS quit_at,
                TIMESTAMPDIFF(SECOND, event_ts, next_ts) / 60.0 AS duration_minutes
            FROM paired
            WHERE action = 'login' AND next_action = 'logout'
            ORDER BY event_ts DESC
            LIMIT $limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, ['key' => $avatarKey]);
    }

    /**
     * New vs returning avatars per week.
     *
     * @return array<array{week:string, new_avatars:int, returning_avatars:int, total_avatars:int}>
     */
    public function getNewVsReturning(): array
    {
        $sql = <<<SQL
            WITH first_logins AS (
                SELECT avatar_key, MIN(DATE(event_ts)) AS first_day
                FROM event
                WHERE action = 'login'
                GROUP BY avatar_key
            ),
            weekly AS (
                SELECT
                    DATE(event_ts) - INTERVAL (DAYOFWEEK(event_ts) - 2) DAY AS week_start,
                    COUNT(DISTINCT e.avatar_key) AS total_avatars,
                    COUNT(DISTINCT CASE WHEN DATE(e.event_ts) = fl.first_day THEN e.avatar_key END) AS new_avatars
                FROM event e
                JOIN first_logins fl USING (avatar_key)
                WHERE e.action = 'login'
                GROUP BY week_start
            )
            SELECT
                week_start AS week,
                new_avatars,
                total_avatars - new_avatars AS returning_avatars,
                total_avatars
            FROM weekly
            ORDER BY week
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Session duration distribution buckets.
     *
     * @return array<array{bucket:string, count:int}>
     */
    public function getDurationDistribution(): array
    {
        $sql = <<<SQL
            WITH paired AS (
                SELECT
                    action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action
                FROM event
            ),
            durations AS (
                SELECT TIMESTAMPDIFF(MINUTE, event_ts, next_ts) AS minutes
                FROM paired
                WHERE action = 'login' AND next_action = 'logout'
            )
            SELECT
                CASE
                    WHEN minutes < 5   THEN '< 5 min'
                    WHEN minutes < 15  THEN '5-15 min'
                    WHEN minutes < 30  THEN '15-30 min'
                    WHEN minutes < 60  THEN '30-60 min'
                    WHEN minutes < 120 THEN '1-2 hrs'
                    ELSE '2+ hrs'
                END AS bucket,
                COUNT(*) AS count
            FROM durations
            GROUP BY bucket
            ORDER BY MIN(minutes)
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * Scatter: login frequency vs average session duration per avatar.
     *
     * @return array{display_name:string, avatar_key:string, visit_count:int, avg_minutes:float, total_minutes:float}>
     */
    public function getFrequencyVsDuration(): array
    {
        $sql = <<<SQL
            WITH paired AS (
                SELECT
                    avatar_key, display_name, action, event_ts,
                    LEAD(event_ts) OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_ts,
                    LEAD(action)   OVER (PARTITION BY avatar_key ORDER BY event_ts) AS next_action,
                    ROW_NUMBER()   OVER (PARTITION BY avatar_key ORDER BY event_ts DESC) AS rn
                FROM event
            )
            SELECT
                avatar_key,
                MAX(CASE WHEN rn = 1 THEN display_name END) AS display_name,
                COUNT(CASE WHEN action = 'login' AND next_action = 'logout' THEN 1 END) AS visit_count,
                AVG(CASE WHEN action = 'login' AND next_action = 'logout'
                    THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS avg_minutes,
                SUM(CASE WHEN action = 'login' AND next_action = 'logout'
                    THEN TIMESTAMPDIFF(SECOND, event_ts, next_ts) END) / 60.0 AS total_minutes
            FROM paired
            GROUP BY avatar_key
            HAVING visit_count >= 2
            ORDER BY visit_count DESC
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);
    }

    /**
     * All distinct avatar keys ever seen in the event log, ordered alphabetically.
     *
     * @return string[]
     */
    public function findDistinctAvatarKeys(): array
    {
        return $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn('SELECT DISTINCT avatar_key FROM event ORDER BY avatar_key');
    }
}
