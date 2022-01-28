<?php
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');

session_start();

// 投稿データを取得。紐づく会員情報も結合し同時に取得する。
$select_sth = $dbh->prepare(
  'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' ORDER BY bbs_entries.created_at DESC'
);
$select_sth->execute();

// bodyのHTMLを出力するための関数を用意する
function bodyFilter (string $body): string
{
    $body = htmlspecialchars($body); // エスケープ処理
    $body = nl2br($body); // 改行文字を<br>要素に変換

    // >>1 といった文字列を該当番号の投稿へのページ内リンクとする (レスアンカー機能)
    // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
    $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

    return $body;
}
?>

<?php if(empty($_SESSION['login_user_id'])): ?>
  <a href="/login.php">ログイン</a>して自分のタイムラインを閲覧しましょう！
<?php else: ?>
  <a href="/timeline.php">タイムラインはこちら</a>
<?php endif; ?>
<hr>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt id="entry<?= htmlspecialchars($entry['id']) ?>">
      番号
    </dt>
    <dd>
      <?= htmlspecialchars($entry['id']) ?>
    </dd>
    <dt>
      投稿者
    </dt>
    <dd>
      <a href="/profile.php?user_id=<?= $entry['user_id'] ?>">
        <?php if(!empty($entry['user_icon_filename'])): // アイコン画像がある場合は表示 ?>
        <img src="/image/<?= $entry['user_icon_filename'] ?>"
          style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
        <?php endif; ?>

        <?= htmlspecialchars($entry['user_name']) ?>
        (ID: <?= htmlspecialchars($entry['user_id']) ?>)
      </a>
    </dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= bodyFilter($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>
