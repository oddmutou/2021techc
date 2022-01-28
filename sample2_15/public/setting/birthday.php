<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');
// セッションにあるログインIDから、ログインしている対象の会員情報を引く
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $_SESSION['login_user_id'],
]);
$user = $select_sth->fetch();

if (isset($_POST['birthday'])) {
  // フォームから birthday が送信されてきた場合の処理

  // ログインしている会員情報のbirthdayカラムを更新する
  $update_sth = $dbh->prepare("UPDATE users SET birthday = :birthday WHERE id = :id");
  $update_sth->execute([
      ':id' => $user['id'],
      ':birthday' => $_POST['birthday'],
  ]);
  // 成功したら成功したことを示すクエリパラメータつきのURLにリダイレクト
  header("HTTP/1.1 302 Found");
  header("Location: ./birthday.php?success=1");
  return;
}
?>
<a href="./index.php">設定一覧に戻る</a>

<h1>生年月日</h1>
<form method="POST">
  <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>">
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['success'])): ?>
<div>
  生年月日の変更処理が完了しました。
</div>
<?php endif; ?>
