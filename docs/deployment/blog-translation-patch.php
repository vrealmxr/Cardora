<?php
// Shared by the reviewed one-off CLI deployment and memory-only database tests.
function prepareBlogTranslationRows($db, array $manifest, bool $rollback = false): array
{
    $plans = [];
    $seen = [];
    foreach ($manifest as $entry) {
        $id = $entry['id'];
        if (isset($seen[$id])) throw new RuntimeException('Duplicate article');
        $seen[$id] = true;
        $row = $db->table('blog_posts')->where('id', $id)->lockForUpdate()->first();
        $expected = $rollback ? $entry['afterSha256'] : $entry['rawContentSha256'];
        if (!$row || $row->slug !== $entry['slug'] || $row->status !== 'published'
            || !hash_equals($expected, hash('sha256', $row->content))) {
            throw new RuntimeException('Article changed or is not published: '.$id);
        }
        if ($rollback) {
            $replacement = $entry['beforeContent'];
            if (!hash_equals($entry['beforeSha256'], hash('sha256', $replacement))) throw new RuntimeException('Invalid backup');
        } else {
            $content = json_decode($row->content, true, 512, JSON_THROW_ON_ERROR);
            $el = $content['translations']['el'] ?? null;
            $en = $entry['en'];
            if (array_key_exists('en', $content['translations'] ?? []) || !$el) throw new RuntimeException('Existing English or missing Greek content');
            if (array_diff(array_keys($en), ['intro','sections','source_note']) || empty($en['intro'])
                || count($en['sections'] ?? []) !== count($el['sections'])
                || array_key_exists('source_note', $en) !== array_key_exists('source_note', $el)
                || preg_match('/[\x{0370}-\x{03ff}]/u', json_encode($en, JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('Incomplete English content');
            }
            foreach ($en['sections'] as $i => $section) {
                if (array_diff(array_keys($section), ['title','paragraphs','bullets']) || empty($section['title'])
                    || count($section['paragraphs'] ?? []) !== count($el['sections'][$i]['paragraphs'])
                    || count($section['bullets'] ?? []) !== count($el['sections'][$i]['bullets'])) {
                    throw new RuntimeException('Section structure differs');
                }
                foreach (array_merge($section['paragraphs'], $section['bullets']) as $text) {
                    if (!is_string($text) || trim($text) === '') throw new RuntimeException('Empty English text');
                }
            }
            $content['translations']['en'] = $en;
            $replacement = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        $plans[] = ['id'=>$id, 'slug'=>$row->slug, 'beforeContent'=>$row->content,
            'beforeSha256'=>hash('sha256',$row->content), 'afterContent'=>$replacement,
            'afterSha256'=>hash('sha256',$replacement), 'beforeRow'=>(array)$row];
    }
    return $plans;
}

function applyBlogTranslationRows($db, array $plans): void
{
    foreach ($plans as $plan) {
        if ($db->table('blog_posts')->where('id',$plan['id'])->update(['content'=>$plan['afterContent']]) !== 1) {
            throw new RuntimeException('Unexpected update count');
        }
        $after = (array)$db->table('blog_posts')->where('id',$plan['id'])->first();
        $expected = $plan['beforeRow'];
        $expected['content'] = $plan['afterContent'];
        if ($after !== $expected) throw new RuntimeException('Unexpected field change');
    }
}
