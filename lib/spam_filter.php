<?php

/**
 * spam_filter.php
 *
 * Based on akismet_filter.php version 1.2
 *  author Akio KONUMA konuma@ark-web.jp
 *  link http://www.ark-web.jp/sandbox/wiki/190.html
 *
 * @authoer SATOH Kiyoshi (satoh at hakuba dot jp)
 * @link http://miasa.info/index.php?%C8%FE%CB%E3Wiki%A4%C7%A5%B7%A5%B9%A5%C6%A5%E0%C5%AA%A4%CB%BD%A4%C0%B5%A4%B7%A4%C6%A4%A4%A4%EB%C5%C0
 * @version 0.7.6
 * @license GPL v2 or (at your option) any later version
 */

/*
 * 修正情報
 *
 * PukiWiki1.5.2用スパムフィルタspam_filter.php
 *
 * ・akismet.class.php → Akismet PHP5 Class に置き換え
 * →https://github.com/achingbrain/php5-akismet
 *
 * ※「lib」フォルダにあるPukiWikiプログラムを1.5.2用に修正
 * 　「Net」フォルダのファイルは文字コードをUTF-8に変更したのみ
 * 　delegated-apnic-latestファイルは最新(2019/06/01)に更新
 *
 * @author		オヤジ戦隊ダジャレンジャー <red@dajya-ranger.com>
 * @copyright	Copyright © 2019, dajya-ranger.com
 * @link		https://dajya-ranger.com/pukiwiki/embed-url-shortener/
 * @example		@linkの内容を参照
 * @license		Apache License 2.0
 * @version		0.8.1
 * @since 		0.8.1 2019/06/01 スパム判定した場合に「spam_filter」フォルダにログを出力するように修正
 * @since 		0.8.0 暫定初公開（ソースをPukiWiki1.5.2に移植）
 *
 */

//// pukiwiki.ini.phpなどで各スパムフィルタの利用とフィルタ毎の指定をする
// 設定内容の命名規則と内容
// SPAM_FILTER_****_PLUGIN_NAME -> チェック対象とするプラグイン名。カンマ区切り
// SPAM_FILTER_****_REG         -> マッチさせる正規表現
// SPAM_FILTER_****_URLREG      -> URLを識別するための正規表現
// SPAM_FILTER_****_WHITEREG    -> マッチしなくてよいURLホワイトリスト

//// スパムと判断する条件を指定する
// 指定された各種スパムフィルタを全部通ったらFALSE
// 軽いフィルタから順に掛け、false positiveの可能性があるものは複合条件で掛ける
// ※SPAM_FILTER_COND 設定例
// ※UserAgentがlibwww等、HTMLの添付ファイル、</a>タグ等がある、英語のみでURLが3つ以上、URLのNSのブラックリスト
//define('SPAM_FILTER_COND', '#useragent() or #filename() or #atag() or (#onlyeng() and #urlnum()) or #urlnsbl()');
// ※上記条件にプラス、英語のみのときでURLがあるときだけAkismetで精査
//define('SPAM_FILTER_COND', '#useragent() or #filename() or #atag() or (#onlyeng() and #urlnum()) or #urlnsbl() or (#onlyeng() and #url() and #akismet())');
// ※上記条件にメールフォームでreCAPTCHAを指定する場合
//define('SPAM_FILTER_COND', ''#useragent() or #filename() or #atag() or (#onlyeng() and #urlnum()) or #urlnsbl() or (#onlyeng() and #url() and #akismet()) or #recaptcha()');
// ※デフォルトではフィルタなし
define('SPAM_FILTER_COND', '');

//// CAPTCHAでのチェックをする条件を指定する
// ※SPAM_FILTER_CONDで明示的に「#recaptcha()」を指定することにより廃止
//define('SPAM_FILTER_CAPTCHA_COND', '');

//// 各フィルタ共通で設定できる指定
// URLでのマッチで自ドメインなどの無視すべきURL
define('SPAM_FILTER_WHITEREG', '/example\.(com|net|jp)/i');
// URLを抽出する際の正規表現
define('SPAM_FILTER_URLREG', '/(?:(?:https?|ftp|news):\/\/)[\w\/\@\$()!?&%#:;.,~\'=*+-]+/i');

//// urlnsbl などで使う、NSの取得をする dns_get_ns の設定
// NSを引いた結果をある程度キャッシュしておく
define('SPAM_FILTER_DNSGETNS_CACHE_FILE', 'dns_get_ns.cache');
// キャッシュしておく日数
define('SPAM_FILTER_DNSGETNS_CACHE_DAY', 30);
// nslookup コマンドへのパス - PHP4の場合などで必要となる場合がある
//define('SPAM_FILTER_NSLOOKUP_PATH', '/usr/bin/nslookup');

//// ipcountry などで使う、IPから国コードを取得する get_country_code の設定
// IPアドレス帯と国情報の書かれたファイル名
define('SPAM_FILTER_IPCOUNTRY_FILE', 'delegated-apnic-latest');

//// ngreg     - 内容の正規表現フィルタ
// コメント中で許可しない内容の正規表現
define('SPAM_FILTER_NGREG_REG', '');
define('SPAM_FILTER_NGREG_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// url       - 内容にURLっぽいものが含まれているかチェック
define('SPAM_FILTER_URL_REG', '/https?:/i');
define('SPAM_FILTER_URL_PLUGIN_NAME', 'edit,pkwkmail,comment,pcomment,article');

//// atag      - 内容に</A>や[/URL]のようなアンカータグが含まれているかチェック
define('SPAM_FILTER_ATAG_REG', '/<\/a>|\[\/url\]/i');
define('SPAM_FILTER_ATAG_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// onlyeng   - 内容が半角英数のみ(日本語が入っていない)かチェック
define('SPAM_FILTER_ONLYENG_REG', '/\A[!-~\n ]+\Z/');
define('SPAM_FILTER_ONLYENG_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// urlnum    - 内容に含まれているURLが何個以上かチェック
define('SPAM_FILTER_URLNUM_NUM', '3');
define('SPAM_FILTER_URLNUM_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLNUM_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLNUM_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// ipunknown - クライアントのIPが逆引きできるかチェック
define('SPAM_FILTER_IPUNKNOWN_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');

//// ips25r    - クライアントのIPが動的IPっぽい(S25Rにマッチする)かチェック
// S25Rの正規表現
define('SPAM_FILTER_IPS25R_REG', '/(^[^\.]*[0-9][^0-9\.]+[0-9])|(^[^\.]*[0-9]{5})|(^([^\.]+\.)?[0-9][^\.]*\.[^\.]+\..+\.[a-z])|(^[^\.]*[0-9]\.[^\.]*[0-9]-[0-9])|(^[^\.]*[0-9]\.[^\.]*[0-9]\.[^\.]+\..+\.)|(^(dhcp|dialup|ppp|adsl)[^\.]*[0-9])|\.(internetdsl|adsl|sdi)\.tpnet\.pl$/');
define('SPAM_FILTER_IPS25R_PLUGIN_NAME', 'tb');

//// ipbl      - クライアントのIPやホスト名によるフィルタ
// 許可しないIPやホスト名の正規表現
define('SPAM_FILTER_IPBL_REG', '');
define('SPAM_FILTER_IPBL_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');
// ホスト名が見つけられなかったときにも拒否する場合 TRUE
define('SPAM_FILTER_IPBL_UNKNOWN', FALSE);

//// ipdnsbl   - クライアントのIPをDNSBLでチェック
define('SPAM_FILTER_IPDNSBL_DNS', 'dnsbl.spam-champuru.livedoor.com,niku.2ch.net,bsb.spamlookup.net,bl.spamcop.net,all.rbl.jp');
define('SPAM_FILTER_IPDNSBL_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');

//// ipcountry - クライアントのIPの国をチェック
// マッチさせる国を指定する正規表現
define('SPAM_FILTER_IPCOUNTRY_REG', '/(CN|KR|UA)/');
define('SPAM_FILTER_IPCOUNTRY_PLUGIN_NAME', 'edit,pkwkmail,comment,pcomment,article,attach');

//// uaunknown - HTTP_USER_AGENTが既知(pukiwiki.ini.phpで$agentsで指定)かチェック
define('SPAM_FILTER_UAUNKNOWN_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');

//// useragent - HTTP_USER_AGENTによるフィルタ
// 許可しないHTTP_USER_AGENTの正規表現
define('SPAM_FILTER_USERAGENT_REG', '/WWW-Mechanize|libwww/i');
define('SPAM_FILTER_USERAGENT_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');

//// acceptlanguage - HTTP_ACCEPT_LANGUAGEによるフィルタ
// 許可しないHTTP_ACCEPT_LANGUAGEの正規表現
define('SPAM_FILTER_ACCEPTLANGUAGE_REG', '/cn/i');
define('SPAM_FILTER_ACCEPTLANGUAGE_PLUGIN_NAME', 'edit,comment,pcomment,article,attach');

//// filename  - アップロードファイル名によるフィルタ
// アップロードを許可しないファイル名の正規表現
define('SPAM_FILTER_FILENAME_REG', '/\.html$|\.htm$/i');
define('SPAM_FILTER_FILENAME_PLUGIN_NAME', 'attach');

//// formname  - 存在しないはずのフォーム内容があるかチェック
// 存在しないはずのフォーム名の指定、カンマ区切り
define('SPAM_FILTER_FORMNAME_NAME', 'url,email');
define('SPAM_FILTER_FORMNAME_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// urlbl     - URLがブラックリストに入っているか確認
// URLのブラックリスト ホスト名でもIPでも可
// ※wikiwiki.jpのブラックリストを参考
// ※http://wikiwiki.jp/?%A5%D5%A5%A3%A5%EB%A5%BF%A5%EA%A5%F3%A5%B0%A5%C9%A5%E1%A5%A4%A5%F3%B5%DA%A4%D3%A5%A2%A5%C9%A5%EC%A5%B9
define('SPAM_FILTER_URLBL_REG', '/(0451\.net|1\.sa3\.cn|1102213\.com|1234\.hao88cook\.com|1234564898\.h162\.1stxy\.cn|123lineage\.com|136136\.net|16isp\.com|17aa\.com|17tc\.com|18dmm\.com|18dmm\.com|18girl-av\.com|19800602\.com|1boo\.net|1gangmu\.com|1stxy\.cn|1stxy\.net|216\.168\.128\.126|2chjp\.com|453787\.com|500bb\.com|53dns\.com|56jb\.com|59\.36\.96\.140|5xuan\.com|60\.169\.0\.66|60\.171\.45\.134|66\.98\.212\.108|666\.lyzh\.com|6789\.hao88cook\.com|77276\.com|78xian\.com|84878679\.free\.psnic\.cn|853520\.com|8ycn\.com|92\.av366\.com|a\.2007ip\.com|a\.xiazaizhan\.cn|aaa-livedoor\.net|acyberhome\.com|adfka\.com|adult\.zu1\.ru|ahatena\.com|ahwlqy\.com|anemony\.info|angel\.hao88cook\.com|anyboard\.net|areaseo\.com|asdsdgh-jp\.com|askbigtits\.com|aspasp\.h162\.1stxy\.cn|aurasoul-visjp\.com|auto-mouse\.com|auto-mouse\.jp|avl\.lu|avtw1068\.com|baidu\.chinacainiao\.org|baidulink\.com|bailishidai\.com|bbs-qrcode\.com|bbs\.coocbbs\.com|bestinop\.org|beyondgame\.jsphome\.com|bibi520\.com|bibi520\.h20\.1stxy\.cn|bizcn\.com|blog-livedoor\.net|blogplaync\.com|bluell\.cn|blusystem\.com|bosja\.com|cash\.searchbot\.php|cashette\.com|casino\.online|cc\.wzxqy\.com|cetname\.com|cgimembera\.org|cglc\.org|chengzhibing\.com|china-beijing-cpa\.com|chinacainiao\.org|chinacu\.net|chnvip\.net|chouxiaoya\.org|city689\.com|cityhokkai\.com|cn7135\.cn|cnidc\.cn|conecojp\.net|coocbbs\.com|cool\.47555\.com|coolroge\.199\.53dns\.com|cpanel\.php|cyd\.org\.uk|d\.77276\.com|dcun\.cn|dfsm\.jino-net\.ru|dietnavi\.com|din-or\.com|dj5566\.org|djkkk66990\.com|dl\.gov\.cn|do\.77276\.com|down\.136136\.net|down\.eastrun\.net|down123\.net|dtg-gamania\.com|ee28\.cn|efnm\.w170\.bizcn\.com|emarealtor\.com|ff11-info\.com|ffxiforums\.net|fhy\.net|filthyloaded\.com|fizkult\.org|fly\.leryi\.com|fofje\.info|forumup\.us|forumup\.us|ftplin\.com|fxfqiao\.com|gamaniaech\.com|game-click\.com|game-fc2blog\.com|game-mmobbs\.com|game-oekakibbs\.com|game\.16isp\.com|game4enjoy\.net|game62chjp\.net|gamecent\.com|gameloto\.com|games-nifty\.com|gameslin\.net|gamesragnaroklink\.net|gamesroro\.com|gamet1\.com|gameurdr\.com|gameyoou\.com|gamshondamain\.net|ganecity\.com|gangnu\.com|gemnnammobbs\.com|gendama\.jp|geocitygame\.com|geocitylinks\.com|getamped-garm\.com|ggmm52\.com|ghostsoft\.info|girl-o\.com|gogogoo\.com|good1688\.com|goodclup\.com|google\.cn\.mmhk\.cn|grandchasse\.com|gsisdokf\.net|guoxuecn\.com|gwlz\.cn|hao88cook\.com|hao88cook\.xinwen365\.net|haveip\.com|heixiou\.com|hinokihome\.com\.tw|homepage3-nifty\.com|honda168\.net|hosetaibei\.com|hoyoo\.net|hyap98\.com|i5460\.net|i5460\.net|ic-huanao\.com|iframedollars\.biz|ii688\.com|itgozone\.com|ixbt\.com|izmena\.org|j4sb\.com|japan\.k15\.cn|japan213\.com|japangame1\.com|jdnx\.movie721\.cn|jinluandian\.com|joyjc\.com|joynu\.com|jp\.hao88cook\.com|jpgame666\.com|jpgamer\.net|jpgamermt\.com|jplin\.com|jplineage\.com|jplingood\.com|jplinux\.com|jplove888\.com|jpplay\.net|jpragnarokonline\.com|jprmthome\.com|js1988\.com|jsphome\.com|jswork\.jp|jtunes\.com|jtunes\.com|junkmetal\.info|junkmetal\.info|k15\.cn|kaihatu\.com|kanikuli\.net|kaukoo\.com|kele88\.com|kiev\.ua|kingbaba\.cc|kingrou\.w177\.west263\.cn|kingshi\.net|kingtt\.com|kmqe\.com|kortwpk\.com|korunowish\.com|kotonohax\.com|kulike\.com|kuronowish\.net|kyoukk\.com|la-ringtones\.com|lastlineage\.com|lele\.0451\.net|lin2-jp\.com|linainfo\.net|linbbs\.com|lindeliang-36248700\.15\.cnidc\.cn|lineagalink\.com|lineage-info\.com|lineage\.1102213\.com|lineage\.japan213\.com|lineage1bbs\.com|lineage2-ol\.com|lineage2\.japan213\.com|lineage2006\.com|lineage321\.com|lineagecojp\.com|lineagefirst\.com|lineageink\.com|lineagejp-game\.com|lineagejp-game\.com|lineagejp\.com|lineagekin\.com|lineagett\.com|lineinfo-jp\.com|linenew\.com|lingage\.com|lingamesjp\.com|linjp\.net|linkcetou\.com|linrmb\.com|linsssgame\.com|livedoor1\.com|lliinnss\.com|lovejpjp\.com|lovejptt\.com|lovetw\.webnow\.biz|lyadsl\.com|lyftp\.com|lyzh\.com|macauca\.org\.mo|mail\.8u8y\.com|maplestorfy\.com|micro36\.com|mm\.7mao\.com|mmhk\.cn|mogui\.k15\.cn|moguidage\.h81\.1stxy\.net|mojeforum\.net|monforum\.com|movie1945\.com|mumu\.8ycn\.com|nakosi\.com|navseh\.com|netgamelivedoor\.com|nobunaga\.1102213\.com|nothing-wiki\.com|okinawa\.usmc-mccs\.org|okwit\.com|omakase-net\.com|oulianyong\.com|pagead2\.googlesyndication\.com\.mmhk\.cn|pangzigame\.com|phpnet\.us|planetalanismorissette\.info|playerturbo\.com|playncsoft\.net|playsese\.com|plusintedia\.com|pointlink\.jp|potohihi\.com|ptxk\.com|puma163\.com|qbbd\.com|qianwanip\.cn|qiucong\.com|qq\.ee28\.cn|qq756\.com|quicktopic\.com|rabota\.inetbiznesman\.ru|ragnarok-bbs\.com|ragnarok-game\.com|ragnarok-sara\.com|ragnaroklink\.com|ragnarokonlina\.com|ragnarokonline1\.com|ragnarox\.mobi|rarbrc\.com|rb\.17aa\.com|rbtt1\.com|realitsen\.info|rik\.tag-host\.com|riro\.bibi520\.com|rit1\.bibi520\.com|rit2\.bibi520\.com|rmt-lineagecanopus\.com|rmt-navip\.com|rmt-ranloki\.com|rmt-trade\.com|ro-bot\.net|rogamesline\.com|rokonline-jp\.com|rootg\.org|roprice\.com|rormb\.com|s57\.cn|s678\.cn|scandius\.com|sepgon\.com|setsoul\.org|seun\.ru|seun\.ru|sf\.sf325\.com|shakiranudeworld\.info|shoopivdoor\.com|shoopivdoor\.w19\.cdnhost\.cn|skkustp\.itgozone\.com|skoro\.us|skybeisha\.com|slower-qth\.com|slower-qth\.com|stats\.dl\.gov\.cn|suniuqing\.com|suzukl668\.com|taiwanioke\.com|tankhaoz\.com|tbihome\.org|tesekl\.kmip\.net|thewildrose\.net|thtml\.com|tigermain\.w148\.bizcn\.com|tooplogui\.com|toyshop\.com\.tw|trade-land\.net|trans2424\.com|ttbbss123\.com|tulang1\.com|twabout\.com|twb1og\.net|twganwwko\.com|twguoyong\.com|twmsn-ga\.com|twsunkom\.com|twtaipei\.org|ubtop\.com|usmc-mccs\.org|vegas-webspace\.com|w666\.cn|watcheimpress\.com|watchsite\.nm\.ru|web\.77276\.com|webnow\.biz|wenyuan\.com\.cn|west263\.cn|wikiwiKi-game\.com|woowoo\.com\.cn|wowsquare\.com|wulgame\.com|www2\.cw988\.cn|xiaoshuowang\.com\.cn|xintao-01\.woowoo\.com\.cn|xinwen365\.net|xpills\.info|xulao\.com|xx\.wzxqy\.com|xx20062\.kele88\.com|xxlin\.com|xz\.llliao\.com|xzqx88\.com|yahoo-gamebbs\.com|yahoo\.chinacainiao\.org|yangjicook\.com|yingzhiyuan\.com|yohoojp\.com|youshini\.com|youtnwaht\.tw\.cn|youxigg\.com|yujinmp\.com|ywdgigkb-jp\.com|yzlin\.com|zaprosov\.com|zhangweijp\.com|zhangweijp\.w100\.okwit\.com|zhangwenbin-tian1\.14\.cnidc\.cn|zixinzhu\.cn|zn360\.com|zoo-sex\.com\.ua|ok8vs\.com|blog-ekndesign\.com|gamesmusic-realcgi\.net|homepage-nifty\.com|jpxpie6-7net\.com|irisdti-jp\.com|plusd-itmedia\.com|runbal-fc2web\.com|jklomo-jp\.com|d-jamesinfo\.com|deco030-cscblog\.com|ie6xp\.com|gomeodc\.com|vviccd520\.com|ipqwe\.com|mumy8\.com|okvs8\.com|p5ip\.com|plmq\.com|y8ne\.com|yyc8\.com|cityblog-fc2web\.com|extd-web\.com|gamegohi\.com|a-hatena\.com|ragnarok-search\.com|23styles\.com|ezbbsy\.com|livedoor-game\.com|m-phage\.com|yy14-kakiko\.com|lian-game\.com|ezbbs\.com|dentsu\.itgo\.com)/i');
define('SPAM_FILTER_URLBL_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLBL_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLBL_PLUGIN_NAME', 'edit,comment,pcomment,article');
// IPが見つけられなかったときにも拒否する場合 TRUE
define('SPAM_FILTER_URLBL_UNKNOWN', FALSE);

//// urlcountry  - URLのサーバのある国をチェック
// マッチさせる国を指定する正規表現
define('SPAM_FILTER_URLCOUNTRY_REG', '/(CN|KR|UA)/');
define('SPAM_FILTER_URLCOUNTRY_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLCOUNTRY_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLCOUNTRY_PLUGIN_NAME', 'edit,comment,pcomment,article');

//// urldnsbl  - URLがDNSBLに入っているか確認
// DNSBLのリスト
define('SPAM_FILTER_URLDNSBL_DNS', 'url.rbl.jp,rbl.bulkfeeds.jp,multi.surbl.org,list.uribl.com,bsb.spamlookup.net');
define('SPAM_FILTER_URLDNSBL_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLDNSBL_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLDNSBL_PLUGIN_NAME', 'edit,pkwkmail,comment,pcomment,article');

//// urlnsbl   - URLのNSがブラックリストに入っているか確認
// URLのNSのブラックリスト ホスト名でもIPでも可
// ※wikiwiki.jpのブラックリストを参考
// ※http://wikiwiki.jp/?%A5%D5%A5%A3%A5%EB%A5%BF%A5%EA%A5%F3%A5%B0%A5%C9%A5%E1%A5%A4%A5%F3%B5%DA%A4%D3%A5%A2%A5%C9%A5%EC%A5%B9
define('SPAM_FILTER_URLNSBL_REG', '/(\.dnsfamily\.com|\.xinnet\.cn|\.xinnetdns\.com|\.bigwww\.com|\.4everdns\.com|\.myhostadmin\.net|\.dns\.com\.cn|\.hichina\.com|\.cnmsn\.net|\.focusdns\.com|\.cdncenter\.com|\.cnkuai\.cn|\.cnkuai\.com|\.cnolnic\.com|\.dnspod\.net|\.mywebserv\.com|216\.195\.58\.5[0-9])/i');
define('SPAM_FILTER_URLNSBL_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLNSBL_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLNSBL_PLUGIN_NAME', 'edit,comment,pcomment,article');
// NSが見つけられなかったときにも拒否する場合 TRUE
define('SPAM_FILTER_URLNSBL_NSUNKNOWN', FALSE);

//// urlnscountry - URLのNSの国をチェック
// マッチさせる国を指定する正規表現
define('SPAM_FILTER_URLNSCOUNTRY_REG', '/(CN|KR|UA)/');
define('SPAM_FILTER_URLNSCOUNTRY_WHITEREG', SPAM_FILTER_WHITEREG);
define('SPAM_FILTER_URLNSCOUNTRY_URLREG', SPAM_FILTER_URLREG);
define('SPAM_FILTER_URLNSCOUNTRY_PLUGIN_NAME', 'edit,comment,pcomment,article');
// NSが見つけられなかったときにも拒否する場合 TRUE
define('SPAM_FILTER_URLNSCOUNTRY_NSUNKNOWN', FALSE);

//// akismet   - Akismet によるフィルタ
// スパムチェック時には無視するPostデータ。カンマ区切り
define('SPAM_FILTER_AKISMET_IGNORE_KEY', 'digest');
// Akismetで取得する。APIキー
define('SPAM_FILTER_AKISMET_API_KEY', '');
define('SPAM_FILTER_AKISMET_PLUGIN_NAME', '');

//// reCAPTCHA の設定
// reCAPTCHA種別（'v2'または'v3'それ以外はreCAPTCHAを実行しない）
define('SPAM_FILTER_RECAPTCHA_CHECK', '');
// Bot識別スコア閾（しきい）値（0.5がBotと人間の閾値でスコアが低いほどBot）
define('SPAM_FILTER_RECAPTCHA_THRESHOLD', '0.5');
// reCAPTCHAを実行するプラグイン（例：'edit,article,pkwkmail'）
define('SPAM_FILTER_RECAPTCHA_PLUGIN_NAME', 'edit,article,pkwkmail');
// サイトキー
define('SPAM_FILTER_RECAPTCHA_SITEKEY', '');
// シークレットキー
define('SPAM_FILTER_RECAPTCHA_SECRETKEY', '');


define('SPAM_FILTER_IS_WINDOWS', (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'));

//// スパムフィルタ本体
// plugin.php から呼ばれる
function spam_filter($plugin)
{
	global $vars;
    $spamfilter = new SpamFilter($_POST, $plugin);

	// クライアント情報
	$hp = $script;
	$ua   = $_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : '';
	$addr = $_SERVER['REMOTE_ADDR'];
	if (! $_SERVER['REMOTE_HOST']) {
		$_SERVER['REMOTE_HOST'] = gethostbyaddr($addr);
	}
	$host = $_SERVER['REMOTE_HOST'];
	// PukiWiki情報
	$ref  = isset($vars['refer']) ? $vars['refer'] : '';
	$page = isset($vars['page']) ? $vars['page'] : '';
	$page = $page == '' ? $ref : $page;

    // スパムチェック
    if ($spamfilter->is_spam()) {
		// スパムログを「spam_filter」フォルダに出力するように修正
		$log  = strftime('%y/%m/%d %H:%M:%S') . "\t" . $addr . "\t";
		$log .= $host . "\t" . $page . "\t" . $spamfilter->message . "\n";
		$fp = fopen(DATA_HOME . 'spam_filter/' . strftime('%y%m%d') . '.ignore.log', 'a');
		fwrite($fp, $log);
		fclose($fp);
		die_message( "Spam check failed. Plugin:". $spamfilter->plugin_name ." Match:". $spamfilter->message ."<br>\n" );
	}

}


//// スパムフィルタクラス
// フィルタ用の短い関数名で名前空間を汚さないためクラスでまとめたもの
class SpamFilter
{
    // 各スパムフィルタで参照するデータ
    var $post_data;   // 投稿された内容
    var $plugin_name; // 呼び出されたプラグイン名
    var $message;     // エラー出力用にマッチした条件などを追記していく
    var $dns_get_ns_cache; // dns_get_nsのキャッシュ用

    function SpamFilter($post, $plugin)
    {
        $this->post_data = $post;
        $this->plugin_name = $plugin;
        $this->message = '';
    }

    // SPAM_FILTER_COND で指定されたスパムフィルタを掛ける
    function is_spam($cond = SPAM_FILTER_COND)
    {
        // edit で preview のときはチェック掛けない
        global $vars;
        if ($this->plugin_name == 'edit' && isset($vars['preview'])) return FALSE;
        // フィルタ条件の指定がなければそのまま返る
        if (preg_match('/^\s*$/', $cond)) return FALSE;

        // マッチした条件を書き出すバッファをクリア
        $this->message = '';
        // フィルタ条件を整形してからチェック掛ける
        $cond = preg_replace('/#/', '$this->', $cond);
        $cond = 'return('. $cond .');';
        return eval( $cond );
    }

    function check_plugin($pluginnames)
    {
        $plugin_names = explode(",", $pluginnames);
        return in_array($this->plugin_name, $plugin_names);
    }

    // 内容の正規表現チェック
    function ngreg($reg = SPAM_FILTER_NGREG_REG,
                   $pluginnames = SPAM_FILTER_NGREG_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        if (preg_match($reg, $this->post_data['msg'])) {
            $this->message .= 'ngreg ';
            return TRUE;
        }

        return FALSE;
    }

    // 内容にURLが含まれているかチェック
    function url($reg = SPAM_FILTER_URL_REG,
                 $pluginnames = SPAM_FILTER_URL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        if (preg_match($reg, $this->post_data['msg'])) {
            $this->message .= 'url ';
            return TRUE;
        }

        return FALSE;
    }

    // 内容に</A>や[/URL]のようなアンカータグが含まれているかチェック
    function atag($reg = SPAM_FILTER_ATAG_REG,
                  $pluginnames = SPAM_FILTER_ATAG_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        if (preg_match($reg, $this->post_data['msg'])) {
            $this->message .= 'atag ';
            return TRUE;
        }

        return FALSE;
    }

    // 内容が半角英数のみ(日本語が入っていない)かチェック
    function onlyeng($reg = SPAM_FILTER_ONLYENG_REG,
                     $pluginnames = SPAM_FILTER_ONLYENG_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        if (preg_match($reg, $this->post_data['msg'])) {
            $this->message .= 'onlyeng ';
            return TRUE;
        }

        return FALSE;
    }

    // 内容に含まれているURLが何個以上かチェック
    function urlnum($num = SPAM_FILTER_URLNUM_NUM,
                    $whitereg = SPAM_FILTER_URLNUM_WHITEREG,
                    $urlreg = SPAM_FILTER_URLNUM_URLREG,
                    $pluginnames = SPAM_FILTER_URLNUM_PLUGIN_NAME)
    {
        //        die_message("in urlnum plugin_name". $this->plugin_name);
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $url)) continue;

            // ホワイトリストにマッチしなかったときはカウントアップ
            $link_count ++;
        }
        if ($link_count >= $num) {
            $this->message .= 'urlnum ';
            return TRUE;
        }

        return FALSE;
    }

    // クライアントのIPが逆引きできるかチェック
    function ipunknown($pluginnames = SPAM_FILTER_IPUNKNOWN_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // IPが設定されていない場合は調べられないので通す
        if (empty($_SERVER['REMOTE_ADDR'])) return FALSE;

        $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        if (empty($hostname)) {
            $this->message .= 'ipunknown ';
            return TRUE;
        }

        return FALSE;
    }

    // クライアントのIPが動的IPっぽい(S25Rにマッチする)かチェック
    function ips25r($reg = SPAM_FILTER_IPS25R_REG,
                    $pluginnames = SPAM_FILTER_IPS25R_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // IPが設定されていない場合は調べられないので通す
        if (empty($_SERVER['REMOTE_ADDR'])) return FALSE;

        $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        if (empty($hostname) || preg_match($reg, $hostname)) {
            $this->message .= 'ips25r ';
            return TRUE;
        }

        return FALSE;
    }

    // クライアントのIPのチェック
    function ipbl($reg = SPAM_FILTER_IPBL_REG,
                  $pluginnames = SPAM_FILTER_IPBL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // IPが設定されていない場合は調べられないので通す
        if (empty($_SERVER['REMOTE_ADDR'])) return FALSE;

        $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        if (preg_match($reg, $_SERVER['REMOTE_ADDR']) ||
            preg_match($reg, $hostname)) {
            $this->message .= 'ipbl ';
            return TRUE;
        }
        if (SPAM_FILTER_IPBL_UNKNOWN && empty($hostname)) {
            $this->message .= 'ipbl(unknown) ';
            return TRUE;
        }

        return FALSE;
    }

    // クライアントのIPをDNSBLでチェック
    function ipdnsbl($dnss = SPAM_FILTER_IPDNSBL_DNS,
                     $pluginnames = SPAM_FILTER_IPDNSBL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // IPが設定されていない場合は調べられないので通す
        if (empty($_SERVER['REMOTE_ADDR'])) return FALSE;

        $dns_hosts = explode(",", $dnss);
        $ip = $_SERVER['REMOTE_ADDR'];
        $revip = implode('.', array_reverse(explode('.', $ip)));

        foreach ($dns_hosts as $dns) {
            $lookup = $revip . '.' . $dns;
            $result = gethostbyname($lookup);
            if ($result != $lookup) {
                $this->message .= 'ipdnsbl ';
                return TRUE;
            }
        }

        return FALSE;
    }

    // クライアントのIPの国をチェック
    function ipcountry($reg = SPAM_FILTER_IPCOUNTRY_REG,
                       $pluginnames = SPAM_FILTER_IPCOUNTRY_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // IPが設定されていない場合は調べられないので通す
        if (empty($_SERVER['REMOTE_ADDR'])) return FALSE;

        $country = $this->get_country_code( $_SERVER['REMOTE_ADDR'] );
        if (preg_match($reg, $country)) {
            $this->message .= 'ipcountry ';
            return TRUE;
        }

        return FALSE;
    }

    // HTTP_USER_AGENTが既知(pukiwiki.ini.phpで$agentsで指定)かチェック
    function uaunknown($pluginnames = SPAM_FILTER_UAUNKNOWN_PLUGIN_NAME)
    {
        global $agents;

        if (!$this->check_plugin($pluginnames)) return FALSE;

        // UserAgent値が設定されていない場合は拒否
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->message .= 'uaunknown(empty) ';
            return TRUE;
        }

        // $agentsの最後にあるdefault条件以外とマッチさせる
        $agents_temp = $agents;
        array_pop( $agents_temp );
        foreach ($agents_temp as $agent) {
            // どれかのUAとマッチしたら問題なし
            if (preg_match($agent['pattern'], $_SERVER['HTTP_USER_AGENT'])) return FALSE;
        }
        // どのUAともマッチしなかった
        $this->message .= 'uaunknown ';
        return TRUE;
    }

    // HTTP_USER_AGENTのチェック
    // ※使用には HTTP_USER_AGENT を消さないよう init.php へパッチの必要あり
    function useragent($reg = SPAM_FILTER_USERAGENT_REG,
                       $pluginnames = SPAM_FILTER_USERAGENT_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // UserAgent値が設定されていない場合は拒否
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->message .= 'uaunknown(empty) ';
            return TRUE;
        }

        if (preg_match($reg, $_SERVER['HTTP_USER_AGENT'])) {
            $this->message .= 'useragent ';
            return TRUE;
        }

        return FALSE;
    }

    // HTTP_ACCEPT_LANGUAGEのチェック
    function acceptlanguage($reg = SPAM_FILTER_ACCEPTLANGUAGE_REG,
                            $pluginnames = SPAM_FILTER_ACCEPTLANGUAGE_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // AcceptLanguage値が設定されていない場合は拒否
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $this->message .= 'alunknown(empty) ';
            return TRUE;
        }

        if (preg_match($reg, $_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $this->message .= 'acceptlanguage ';
            return TRUE;
        }

        return FALSE;
    }

    // アップロードファイル名によるフィルタ
    function filename($reg = SPAM_FILTER_FILENAME_REG,
                      $pluginnames = SPAM_FILTER_FILENAME_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        if (isset($_FILES['attach_file'])) {
            $file = $_FILES['attach_file'];
            if (preg_match($reg, $file['name'])) {
                $this->message .= 'filename ';
                return TRUE;
            }
        }

        return FALSE;
    }

    // 存在しないはずのフォーム内容があるかチェック
    function formname($formnames = SPAM_FILTER_FORMNAME_NAME,
                      $pluginnames = SPAM_FILTER_FORMNAME_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 指定された名前のフォームの内容がなにかあるか確認
        $form_names = explode(",", $formnames);
        foreach ($form_names as $name) {
            if (!empty($this->post_data["$name"])) {
                $this->message .= 'formname ';
                return TRUE;
            }
        }

        return FALSE;
    }

    // URLがブラックリストに入っているか確認
    function urlbl($reg = SPAM_FILTER_URLBL_REG,
                   $whitereg = SPAM_FILTER_URLBL_WHITEREG,
                   $urlreg = SPAM_FILTER_URLBL_URLREG,
                   $pluginnames = SPAM_FILTER_URLBL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // URLのホスト名からドメインを得る
            $url_array = parse_url($url);
            $hostname = $url_array['host'];

            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $hostname)) continue;

            // ホスト名をブラックリストと照らし合わせ
            if (preg_match($reg, $hostname)) {
                $this->message .= 'urlbl(name) ';
                return TRUE;
            }
            // ホスト名のIPをブラックリストと照らし合わせ
            if ($iplist = gethostbynamel($hostname)) {
                foreach ($iplist as $ip) {
                    if (preg_match($reg, $ip)) {
                        $this->message .= 'urlbl(ip) ';
                        return TRUE;
                    }
                }
            }
            else {
                // IPが見つけられなかったときにも拒否する場合
                if (SPAM_FILTER_URLBL_UNKNOWN) {
                    $this->message .= 'urlbl(unknown) ';
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // URLのサーバのある国をチェック
    function urlcountry($reg = SPAM_FILTER_URLCOUNTRY_REG,
                        $whitereg = SPAM_FILTER_URLCOUNTRY_WHITEREG,
                        $urlreg = SPAM_FILTER_URLCOUNTRY_URLREG,
                        $pluginnames = SPAM_FILTER_URLCOUNTRY_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // URLのホスト名を得る
            $url_array = parse_url($url);
            $hostname = $url_array['host'];

            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $hostname)) continue;

            // ホスト名のIPをブラックリストと照らし合わせ
            if ($iplist = gethostbynamel($hostname)) {
                foreach ($iplist as $ip) {
                    $country = $this->get_country_code( $ip );
                    //$tmpmes .= $hostname . ' ' . $ip . ' ' . $country . ', ';
                    if (preg_match($reg, $country)) {
                        $this->message .= 'urlcountry ';
                        return TRUE;
                    }
                }
            }
            else {
                // IPが見つけられなかったときにも拒否する場合
                if (SPAM_FILTER_URLCOUNTRY_UNKNOWN) {
                    $this->message .= 'urlcountry(unknown) ';
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // URLがDNSBLに入っているか確認
    function urldnsbl($dnss = SPAM_FILTER_URLDNSBL_DNS,
                      $whitereg = SPAM_FILTER_URLDNSBL_WHITEREG,
                      $urlreg = SPAM_FILTER_URLDNSBL_URLREG,
                      $pluginnames = SPAM_FILTER_URLDNSBL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        $dns_hosts = explode(",", $dnss);

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $url)) continue;

            // URLのホスト名からドメインを得る
            $url_array = parse_url($url);
            $hostname = $url_array['host'];
            // どこかのDNSBLに登録されてたら
            foreach ($dns_hosts as $dns) {
                $lookup = $hostname . '.' . $dns;
                $result = gethostbyname($lookup);
                if ($result != $lookup) {
                    $this->message .= 'urldnsbl ';
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // URLのNSがブラックリストに入っているか確認
    function urlnsbl($reg = SPAM_FILTER_URLNSBL_REG,
                     $whitereg = SPAM_FILTER_URLNSBL_WHITEREG,
                     $urlreg = SPAM_FILTER_URLNSBL_URLREG,
                     $pluginnames = SPAM_FILTER_URLNSBL_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // URLのホスト名を得る
            $url_array = parse_url($url);
            $hostname = $url_array['host'];

            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $hostname)) continue;

            // ドメインのNSを得る
            if ($this->dns_get_ns($hostname, $nslist)) {
                // ドメインのNSが得られたらNSブラックリストと照らし合わせ
                foreach ($nslist as $ns) {
                    if (preg_match($reg, $ns)) {
                        $this->message .= 'urlnsbl(name) ';
                        return TRUE;
                    }
                    // NSのIPをブラックリストと照らし合わせ
                    if ($iplist = gethostbynamel($ns)) {
                        foreach ($iplist as $ip) {
                            if (preg_match($reg, $ip)) {
                                $this->message .= 'urlnsbl(ip) ';
                                return TRUE;
                            }
                        }
                    }
                }
            }
            else {
                // NSが得られなかった
                if (SPAM_FILTER_URLNSBL_NSUNKNOWN) {
                    $this->message .= 'urlnsbl(unknown) ';
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // URLのNSの国をチェック
    function urlnscountry($reg = SPAM_FILTER_URLNSCOUNTRY_REG,
                          $whitereg = SPAM_FILTER_URLNSCOUNTRY_WHITEREG,
                          $urlreg = SPAM_FILTER_URLNSCOUNTRY_URLREG,
                          $pluginnames = SPAM_FILTER_URLNSCOUNTRY_PLUGIN_NAME)
    {
        if (!$this->check_plugin($pluginnames)) return FALSE;

        // 内容中のURLを抽出
        preg_match_all($urlreg, $this->post_data['msg'], $urls);
        foreach ($urls[0] as $url) {
            // URLのホスト名を得る
            $url_array = parse_url($url);
            $hostname = $url_array['host'];

            // ホスト名がホワイトリストにある場合は無視して次のURLのチェックへ
            if (preg_match($whitereg, $hostname)) continue;

            // ドメインのNSを得る
            if ($this->dns_get_ns($hostname, $nslist)) {
                // ドメインのNSが得られたらその国を調べて、国コードと照らし合わせ
                foreach ($nslist as $ns) {
                    $country = $this->get_country_code( gethostbyname($ns) );
                    if (preg_match($reg, $country)) {
                        $this->message .= 'urlnscountry ';
                        return TRUE;
                    }
                }
            }
            else {
                // NSが得られなかった
                if (SPAM_FILTER_URLNSBL_NSUNKNOWN) {
                    $this->message .= 'urlnscountry(unknown) ';
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    // Akismetによるチェック
    function akismet($pluginnames = SPAM_FILTER_AKISMET_PLUGIN_NAME)
    {

        if (!$this->check_plugin($pluginnames)) return FALSE;

        // akismetクラスの読み込み
        require_once 'Akismet.class.php';

        // Postデータを連結する。
        $ignore_post_keys = explode(",", SPAM_FILTER_AKISMET_IGNORE_KEY);
        foreach ($this->post_data as $key => $val) {
            // ignore_post_keysに設定されているPostデータはAkismetに送らない
            if (!in_array($key, $ignore_post_keys)) {
                $body = $body . $val;
            }
        }

		// Akismetに送信するデータを作成する
		$akismet = new Akismet(get_script_uri(), SPAM_FILTER_AKISMET_API_KEY);
		$akismet->setCommentAuthor('');
		$akismet->setCommentAuthorEmail('');
		$akismet->setCommentAuthorURL('');
		$akismet->setCommentContent($body);
		$akismet->setPermalink('');

		// APIキーチェック
		if (!$akismet->isKeyValid()) {
			die_message('akismet: APIキーが不正です.');
		}

		// スパムチェック
        if ($akismet->isCommentSpam()) {
            $this->message .= 'akismet ';
            return TRUE;
        }

        return FALSE;
    }

    // reCAPTCHAチェック
    function recaptcha($pluginnames = SPAM_FILTER_RECAPTCHA_PLUGIN_NAME)
    {

        if (!$this->check_plugin($pluginnames)) return FALSE;

		$secret_key = SPAM_FILTER_RECAPTCHA_SECRETKEY;

		if (SPAM_FILTER_RECAPTCHA_CHECK =='v2') {
			// reCAPTCHA v2
			$response=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret_key}&response={$_POST['g-recaptcha-response']}");
		} elseif (SPAM_FILTER_RECAPTCHA_CHECK =='v3') {
			// reCAPTCHA v3
			$response=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret_key}&response={$_POST['recaptchaResponse']}");
		} else {
			// SPAM_FILTER_RECAPTCHA_CHECKが設定されていない場合はチェックしない
			return FALSE;
		}

		$recaptcha = json_decode($response, true);
		if(intval($recaptcha["success"]) !== 1) {
			// 認証が成功しなかった場合
			if (SPAM_FILTER_RECAPTCHA_CHECK =='v2') {
				// reCAPTCHA v2
				$this->message .= 'reCAPTCHA ';
				return TRUE;
			} 
		} elseif (SPAM_FILTER_RECAPTCHA_CHECK =='v3') {
			// 認証が成功してもreCAPTCHA v3の場合はスコアを判定する
			if(floatval($recaptcha["score"]) < floatval(SPAM_FILTER_RECAPTCHA_THRESHOLD)) {
				// スコアが設定している閾値より小さい場合は認証エラーとする
				$this->message .= 'reCAPTCHA ';
				return TRUE;
			}
		}

		return FALSE;

	}

    // get DNS server for Windows XP SP2, Vista SP1
    function getDNSServer()
    {
        @exec('ipconfig /all', $ipconfig);
        //print_a($ipconfig, 'label:nameserver');
        foreach ($ipconfig as $line) {
            if (preg_match('/\s*DNS .+:\s+([\d\.]+)$/', $line, $nameservers)) {
                $nameserver = $nameservers[1];
            }
        }
        if (empty($nameserver)) {
            die_message('Can not lookup your DNS server');
        }
        //print_a($nameserver, 'label:nameserver');
        return $nameserver;
    }
    
    //// ホスト名からNSを引くための汎用関数
    // hostnameのドメインのNSをリスト($ns_array)に返す
    // 得られなかった場合は関数の返り値がFALSE
    // ※PHP4の場合、nslookup コマンドが使える必要あり
    function dns_get_ns( $hostname, &$ns_array )
    {
        // 答えを返すところをクリアしておく
        if (!empty($ns_array)) while (array_pop($ns_array));

        // まだキャッシュがなければ以前に得た結果のキャッシュファイルを読み込む
        if (empty($this->dns_get_ns_cache)) {
            $fp = fopen(DATA_HOME . SPAM_FILTER_DNSGETNS_CACHE_FILE, "a+")
                or die_message('Cannot read dns_get_ns cache file: '. SPAM_FILTER_DNSGETNS_CACHE_FILE ."\n");
            flock($fp, LOCK_SH);
            while ($csv = fgetcsv($fp, 1000, ",")) {
                $host = array_shift($csv);
                $time = $csv[0];
                if ($time + SPAM_FILTER_DNSGETNS_CACHE_DAY*24*60*60 < time())
                    continue; // 古すぎる情報は捨てる
                $this->dns_get_ns_cache["$host"] = $csv;
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        // キャッシュの結果に入ってるならそこから結果を引いて返す
        $cache = $this->dns_get_ns_cache["$hostname"];
        if(!empty($cache)) {
            $time = array_shift($cache);
            foreach($cache as $ns) {
                $ns_array[] = $ns;
            }
            return TRUE;
        }

        // ホスト名を上から一つづつ減らしてNSが得られるまで試す
        // 例: www.subdomain.example.com→subdomain.example.com→example.com
        $domain_array = explode(".", $hostname);
        $ns_found = FALSE;
        do {
            $domain = implode(".", $domain_array);

            // 環境で使える手段に合わせてドメインのNSを得る
            if (function_exists('dns_get_record')) {
                // 内部関数 dns_get_record 使える場合
                $lookup = dns_get_record($domain, DNS_NS);
                if (!empty($lookup)) {
                    foreach ($lookup as $record) {
                        $ns_array[] = $record['target'];
                    }
                    $ns_found = TRUE;
                }
            }
            else if (include_once('Net/DNS.php')) {
                // PEARのDNSクラスが使える場合
                $resolver = new Net_DNS_Resolver();
                if (SPAM_FILTER_IS_WINDOWS) $resolver->nameservers[0] = $this->getDNSServer();
                $response = $resolver->query($domain, 'NS');
                if ($response) {
                    foreach ($response->answer as $rr) {
                        if ($rr->type == "NS") {
                            $ns_array[] = $rr->nsdname;
                        }
                        else if ($rr->type == "CNAME") {
                            // CNAMEされてるときは、そっちを再帰で引く
                            $this->dns_get_ns($rr->rdatastr(), $ns_array);
                        }
                    }
                    $ns_found = TRUE;
                }
            }
            else {
                // PEARも使えない場合、外部コマンドnslookupによりNSを取得
                is_executable(SPAM_FILTER_NSLOOKUP_PATH)
                    or die_message("Cannot execute nslookup. see NSLOOKUP_PATH setting.\n");
                @exec(SPAM_FILTER_NSLOOKUP_PATH . " -type=ns " . $domain, $lookup);
                foreach ($lookup as $line) {
                    if( preg_match('/\s*nameserver\s*=\s*(\S+)$/', $line, $ns) ||
                        preg_match('/\s*origin\s*=\s*(\S+)$/', $line, $ns) ||
                        preg_match('/\s*primary name server\s*=\s*(\S+)$/', $line, $ns) ) {
                        $ns_array[] = $ns[1];
                        $ns_found = TRUE;
                    }
                }
            }
        } while (!$ns_found && array_shift($domain_array) != NULL);

        // NSが引けていたら、結果をキャッシュに入れて保存
        if ($ns_found) {
            // 結果をキャッシュに登録
            $cache = $ns_array;
            array_unshift($cache, time()); // 引いた時間も保持
            $this->dns_get_ns_cache["$hostname"] = $cache;

            // キャッシュをファイルに保存
            $fp = fopen(DATA_HOME . SPAM_FILTER_DNSGETNS_CACHE_FILE, "w")
                or die_message("Cannot write dns_get_ns cache file: ". SPAM_FILTER_DNSGETNS_CACHE_FILE ."\n");
            flock($fp, LOCK_EX);
            foreach ($this->dns_get_ns_cache as $host=>$cachedata) {
                $csv = $host;
                foreach ($cachedata as $data) {
                    $csv .= ",". $data;
                }
                $csv .= "\n";
                fputs($fp, $csv);
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $ns_found;
    }

    //// IPアドレスから国コードを引くための汎用関数
    // IPアドレス("10.1.2.3"みたいな文字列)からJPとかの国コードを返す
    // 得られなかった場合はempty('')を返す
    // ※APNICのIPエリアと国の対応コードファイルが必要
    // ※アメリカのIPはリストに無い？
    function get_country_code( $ip_string )
    {
        // まだ国IPリストを読んでなければファイルを読み込んでキャッシュする
        if (empty($this->get_country_code_cache)) {
            $fp = fopen( DATA_HOME . SPAM_FILTER_IPCOUNTRY_FILE, "r")
                or die_message('Cannot read country file: ' . SPAM_FILTER_IPCOUNTRY_FILE . "\n");
            while ($csv = fgetcsv($fp, 1000, "|")) {
                // IPv4だけ対応
                if ($csv[2] === "ipv4") {
                    $country = $csv[1];
                    $ipstring = $csv[3];
                    $ipranges = explode(".", $ipstring);
                    $iprange = ip2long($ipstring);
                    $mask = 256*256*256*256 - $csv[4];
                    $data = new country_data;
                    $data->country = $country;
                    $data->iprange = $iprange;
                    $data->mask = $mask;
                    // Class Aをまたぐ指定は無いのでトップの256で分割して保持
                    $this->get_country_code_cache["$ipranges[0]"][] = $data;
                }
            }
            fclose($fp);
        }

        $ip = ip2long($ip_string);
        $ranges = explode(".", $ip_string);

        $country_code = '';
        foreach ($this->get_country_code_cache["$ranges[0]"] as $data) {
            if ( $data->iprange == ($ip & $data->mask) ) {
                $country_code = $data->country;
                break;
            }
        }

        return $country_code;
    }

}

// get_country_code で保持しているデータ構造
class country_data
{
    var $country;
    var $iprange;
    var $mask;
}

?>
