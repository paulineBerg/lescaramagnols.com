<?php
header('Content-Type: application/rss+xml; charset=UTF-8');

$lang = $_GET['lang'] ?? 'fr';
$files = glob(__DIR__ . '/../data/blog/*.' . $lang . '.json');

echo "<?xml version='1.0' encoding='UTF-8'?>";
?>
<rss version="2.0">
  <channel>
    <title>Les Caramagnols - Actualités</title>
    <link>https://lescaramagnols.com/<?= $lang ?>/blog</link>
    <description>Derniers articles publiés sur Les Caramagnols</description>
    <language><?= $lang ?></language>
    <?php foreach ($files as $file): ?>
      <?php
        $data = json_decode(file_get_contents($file), true);
        if ($data['status'] !== 'published') continue;
        $link = 'https://lescaramagnols.com/' . $lang . '/blog/article/' . $data['slug'];
      ?>
      <item>
        <title><?= htmlspecialchars($data['title']) ?></title>
        <link><?= $link ?></link>
        <guid><?= $link ?></guid>
        <pubDate><?= date(DATE_RSS, strtotime($data['date'])) ?></pubDate>
        <description><![CDATA[<?= strip_tags(substr($data['content'], 0, 300)) ?>...]]></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>
