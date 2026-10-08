<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$db = prototypeConnect();
$db->beginTransaction();
try {
    $db->exec("SET LOCAL lock_timeout='2s'");
    $db->exec("SET LOCAL statement_timeout='10min'");
    $db->exec('SET LOCAL search_path=public');
    // This blocking backfill is suitable ONLY for an idle local test database.
    // Lock before taking the backfill snapshot; no writer can slip between
    // counting existing rows and installing the triggers.
    $db->exec('LOCK TABLE public."user", magazine, entry, entry_comment, post, post_comment,
        entry_vote, entry_comment_vote, post_vote, post_comment_vote, favourite
        IN SHARE ROW EXCLUSIVE MODE');
    $db->exec(file_get_contents(__DIR__.'/setup.sql'));
    $db->commit();
    echo "Installed local counters and triggers.\n";
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
