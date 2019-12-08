# pukiwiki-lib-spam_filter

PukiWiki1.5.2用スパムフィルタ（ライブラリ）spam_filter.php一式

- 暫定公開版です（[Akismet](https://akismet.com/development/)と[reCAPTCHA](https://ja.wikipedia.org/wiki/ReCAPTCHA)以外は十分にテストが出来ていません）
- 設置と設定に関しては自サイトの記事「[PukiWiki1.5.2にスパム対策メールフォームを設置！Googleアドセンスに備える！](https://dajya-ranger.com/pukiwiki/setting-mail-form/)」および「[PukiWiki1.5.2用スパムフィルタをreCAPTCHAに対応！メールフォームをさらに強化！](https://dajya-ranger.com/pukiwiki/setting-mail-form-recaptcha/)」を参照して下さい
- spam_filter.phpの機能の詳細やその設定方法等は[本家](http://miasa.info/index.php?TopPage)「[美麻Wikiでシステム的に修正している点](http://miasa.info/index.php?%C8%FE%CB%E3Wiki%A4%C7%A5%B7%A5%B9%A5%C6%A5%E0%C5%AA%A4%CB%BD%A4%C0%B5%A4%B7%A4%C6%A4%A4%A4%EB%C5%C0#ofa18e88)」ページを参照して下さい
- Ver0.8.1からの変更点は次の通り
	- デフォルトのフィルタを最低限セットするように方針変更
	- empty（空）入力チェックフィルタを新規追加
	- フィルタによってtextareaの内容もチェックするように修正
	- delegated-apnic-latestファイル内容更新（2019/12/08現在）
