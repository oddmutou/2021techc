<?php
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) { // 非ログインの場合利用不可
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// 現在のログイン情報を取得する
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    // 先頭の data:~base64, のところは削る
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    // base64からバイナリにデコードする
    $image_binary = base64_decode($base64);

    // 新しいファイル名を決めてバイナリを出力する
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath =  '/var/www/public/image/' . $image_filename;
    file_put_contents($filepath, $image_binary);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
  $insert_sth->execute([
      ':user_id' => $_SESSION['login_user_id'], // ログインしている会員情報の主キー
      ':body' => $_POST['body'], // フォームから送られてきた投稿本文
      ':image_filename' => $image_filename, // 保存した画像の名前 (nullの場合もある)
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./timeline.php");
  return;
}
?>

<div>
  現在 <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
  <a href="/setting/index.php">設定画面</a>
  /
  <a href="/users.php">会員一覧画面</a>
</div>

<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./timeline.php"><!-- enctypeは外しておきましょう -->
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <input id="imageBase64Input" type="hidden" name="image_base64"><!-- base64を送る用のinput (非表示) -->
  <canvas id="imageCanvas" style="display: none;"></canvas><!-- 画像縮小に使うcanvas (非表示) -->
  <button type="submit">送信</button>
</form>
<hr>

<dl id="entryTemplate" style="display: none; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
  <dt>番号</dt>
  <dd data-role="entryIdArea"></dd>
  <dt>投稿者</dt>
  <dd>
    <a href="" data-role="entryUserAnchor">
      <img data-role="entryUserIconImage"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <span data-role="entryUserNameArea"></span>
    </a>
  </dd>
  <dt>日時</dt>
  <dd data-role="entryCreatedAtArea"></dd>
  <dt>内容</dt>
  <dd data-role="entryBodyArea">
  </dd>
</dl>
<div id="entriesRenderArea"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  // 最後に描画した投稿のIDを保存しておく変数
  let lastRenderedEntryId = null;

  // 投稿を描画する関数
  const renderEntries = () => {
    const entryTemplate = document.getElementById('entryTemplate');
    const entriesRenderArea = document.getElementById('entriesRenderArea');

    const request = new XMLHttpRequest();
    request.onload = (event) => {
      const response = event.target.response;
      response.entries.forEach((entry) => {
        // テンプレートとするものから要素をコピー
        const entryCopied = entryTemplate.cloneNode(true);

        // display: none を display: block に書き換える
        entryCopied.style.display = 'block';

        // id属性を設定しておく(レスアンカ用)
        entryCopied.id = 'entry' + entry.id.toString();

        // 番号(ID)を表示
        entryCopied.querySelector('[data-role="entryIdArea"]').innerText = entry.id.toString();

        // アイコン画像が存在する場合は表示 なければimg要素ごと非表示に
        if (entry.user_icon_file_url.length > 0) {
          entryCopied.querySelector('[data-role="entryUserIconImage"]').src = entry.user_icon_file_url;
        } else {
          entryCopied.querySelector('[data-role="entryUserIconImage"]').display = 'none';
        }

        // 名前を表示
        entryCopied.querySelector('[data-role="entryUserNameArea"]').innerText = entry.user_name;

        // 名前のところのリンク先(プロフィール)のURLを設定
        entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;

        // 投稿日時を表示
        entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;

        // 本文を表示 (ここはHTMLなのでinnerHTMLで)
        entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;

        // 画像が存在する場合に本文の下部に画像を表示
        if (entry.image_file_url.length > 0) {
          const imageElement = new Image();
          imageElement.src = entry.image_file_url; // 画像URLを設定
          imageElement.style.display = 'block'; // ブロック要素にする (img要素はデフォルトではインライン要素のため)
          imageElement.style.marginTop = '1em'; // 画像上部の余白を設定
          imageElement.style.maxHeight = '300px'; // 画像を表示する最大サイズ(縦)を設定
          imageElement.style.maxWidth = '300px'; // 画像を表示する最大サイズ(横)を設定
          entryCopied.querySelector('[data-role="entryBodyArea"]').appendChild(imageElement); // 本文エリアに画像を追加
        }

        // 最後に実際の描画を行う
        entriesRenderArea.appendChild(entryCopied);
      });

      // 最後に描画した投稿のIDを更新
      lastRenderedEntryId = response.last_rendered_entry_id;
      // 最後に描画した投稿がサーバー側にある最後の投稿と異なる(=まだ読み込む投稿がある)場合
      // 一番したの投稿までスクロールした場合に投稿を描画する関数を呼ぶ
      if (lastRenderedEntryId > response.last_entries_id) {
        let observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              observer.unobserve(entry.target);
              renderEntries()
            }
          });
        }, {
          rootMargin: '0px',
          threshold: 1.0
        });
        observer.observe(entriesRenderArea.lastChild);
      }
    }

    // 最後に描画した投稿のIDが設定されていればそれをURLクエリパラメータに設定
    const requestPath = '/timeline_json.php' + (lastRenderedEntryId === null ? '' : '?last_id=' + lastRenderedEntryId.toString());
    request.open('GET', requestPath, true);
    request.responseType = 'json';
    request.send();
  }
  renderEntries();

  // 以下画像縮小用
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
      // 未選択の場合
      return;
    }

    const file = imageInput.files[0];
    if (!file.type.startsWith('image/')){ // 画像でなければスキップ
      return;
    }

    // 画像縮小処理
    const imageBase64Input = document.getElementById("imageBase64Input"); // base64を送るようのinput
    const canvas = document.getElementById("imageCanvas"); // 描画するcanvas
    const reader = new FileReader();
    const image = new Image();
    reader.onload = () => { // ファイルの読み込み完了したら動く処理を指定
      image.onload = () => { // 画像として読み込み完了したら動く処理を指定

        // 元の縦横比を保ったまま縮小するサイズを決めてcanvasの縦横に指定する
        const originalWidth = image.naturalWidth; // 元画像の横幅
        const originalHeight = image.naturalHeight; // 元画像の高さ
        const maxLength = 1000; // 横幅も高さも1000以下に縮小するものとする
        if (originalWidth <= maxLength && originalHeight <= maxLength) { // どちらもmaxLength以下の場合そのまま
            canvas.width = originalWidth;
            canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) { // 横長画像の場合
            canvas.width = maxLength;
            canvas.height = maxLength * originalHeight / originalWidth;
        } else { // 縦長画像の場合
            canvas.width = maxLength * originalWidth / originalHeight;
            canvas.height = maxLength;
        }

        // canvasに実際に画像を描画 (canvasはdisplay:noneで隠れているためわかりにくいが...)
        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        // canvasの内容をbase64に変換しinputのvalueに設定
        imageBase64Input.value = canvas.toDataURL();
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>
