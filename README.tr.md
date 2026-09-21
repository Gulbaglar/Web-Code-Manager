# PHP Kod Yöneticisi (Code Manager)

**Yönetim panelinden özel HTML, CSS ve JavaScript yazın — taslak → sandbox önizleme → yayın akışı, tam sürüm geçmişi, tek tıkla geri alma ve acil durum kapatma düğmesiyle. Türkçe ve İngilizce arayüz.**

🇬🇧 English: [README.md](README.md)

> *Zararı sınırla, geliştiriciyi değil.* Monaco (VS Code) editörüyle gerçek kod yazarsınız; Önizleme, Sürümler, Geri Alma ve Güvenli Mod bir hatanın zararını sınırlar.

Framework gerektirmeyen, bağımsız bir PHP modülü. Sitenizin **iki satır** PHP'ye ihtiyacı var (biri `<head>` içine, biri `</body>` öncesine); kod, sürümler, bileşenler dahil geri kalan her şey yönetim arayüzünden yönetilir ve modülün kendi SQLite dosyasında saklanır (uygulamanızın veritabanına dokunmaz).

## Özellikler

- **Global Kod** — Global CSS, Global JavaScript, Head Kodu, Body Sonu Kodu.
- **Sayfa Kodu** — sayfa başına HTML/CSS/JS. **Yalnızca o sayfada** yüklenir; tek sayfalı uygulamalarda sayfadan çıkınca kaldırılır (sızma yok).
- **Bileşenler** — yeniden kullanılabilir HTML/CSS/JS blokları. Herhangi bir sayfa/bölüm HTML'inde `[component:video-player]` yazın; bileşeni bir kez değiştirin, kullanıldığı her yer güncellenir.
- **Dinamik Bölümler** — *sizin* CSS seçicilerle tanımladığınız adlandırılmış konumlara (hero öncesi/sonrası, footer…) kod ekleyin. Sürükleyerek sıralayın.
- **Sandbox önizleme** — canlıyla *aynı runtime*'a sahip izole iframe, canlı console (`console.log`, hatalar) ve kötü bir döngünün yönetim panelini dondurmaması için sonsuz döngü koruması.
- **Taslak → Önizleme → Yayın** — yayınlamak asla üzerine yazmaz: yeni sürüm oluşturur. Eski sürümü (yeni sürüm olarak) **geri yükleyin**, **karşılaştırın** (diff), eskileri silin.
- **Acil Güvenli Mod** — *Tüm Özel Kodu Kapat* düğmesi; ve herhangi bir URL'ye eklenen `?cm_safe=1` sayfayı özel kodsuz yükler. Özel kod yönetim panelinin içinde asla çalışmaz.
- **Snippet kütüphanesi** — 7 hazır snippet (modal, lightbox, kaydırma animasyonu, önce/sonra kaydırıcı…) + kendi snippet'leriniz. JSON **içe/dışa aktarma**.
- **Hızlı** — global CSS/JS ayrı, önbelleğe alınabilir dosyalardır (revizyona bağlı `immutable` cache); sayfa paketleri yalnızca kullanan sayfaya gömülür.
- **İki arayüz dili (English / Türkçe)** — her sayfanın üstünden seçilir; varsayılan config'den ayarlanır.

## Gereksinimler

`pdo_sqlite` eklentisiyle PHP 8.1+. Composer paketi yok. Monaco editörü jsDelivr'den yüklenir (çevrimdışıyken düz textarea'ya düşer).

## Hızlı başlangıç (demo)

```bash
git clone <bu repo> php-code-manager && cd php-code-manager
php -S localhost:8080 -t public
```

- `http://localhost:8080/` ve `/about.php` — küçük bir demo site.
- `http://localhost:8080/admin/` — Kod Yöneticisi (ilk ziyarette şifrenizi oluşturun).

Deneyin: **Global CSS**'i açın, `header{outline:2px solid tomato}` yazın, **Önizle**, **Yayınla**, demo siteyi yenileyin. Sonra *Hero sonrası* konumunda bir **Dinamik Bölüm** oluşturun.

## Sitenize ekleyin

1. Modülü sitenizin yanına koyun ve web'e **yalnızca `public/`**'ı açın (ya da dosyalarını bir alt klasöre kopyalayın — o zaman `public/admin/_boot.php` içindeki `require`'ı ve config'teki `urls`'i düzenleyin).
2. `config/config.example.php` → `config/config.php` kopyalayın ve sitenizi tanımlayın:
   - `routes` — sayfalarınız (`home`, `contact`, `page:hakkimizda` …)
   - `slots` — bölümlerin eklenebileceği yerler: sayfanızdaki bir CSS seçici + `beforebegin | afterbegin | beforeend | afterend`
   - `urls` — `serve.php` ve `assets/cc-runtime.js`'in tarayıcıdan erişildiği adresler
   - `preview` — CSS dosyalarınız (gömülür) ve sayfalarınızla aynı seçicilere sahip bir HTML iskeleti; böylece önizleme gerçek siteye benzer
3. Her sayfa şablonunda:

```php
<?php require '/yol/php-code-manager/src/bootstrap.php'; ?>
<head> … <?php CodeManager\Frontend::head(); ?> </head>
<body> … <?php CodeManager\Frontend::footer('home'); ?> </body>   <!-- 'home' = bu sayfanın rota anahtarı -->
```

### Tek sayfalı uygulamalar (SPA)

`footer()`'ın otomatik başlatmasını kullanmayın: her rota değişiminde `serve.php?route=…&sub=…&v=<rev>` adresinden paketi alıp
`CodeManagerRuntime.apply(bundle, { route, sub, lang, root: element, slots: CodeManagerConfig.slots })` çağırın
(`apply()` önce önceki rotayı temizler). Ayrıntı için `public/assets/cc-runtime.js` başındaki yorumlara bakın.

## Özel JavaScript yazmak

Sayfa/bölüm/bileşen JS'i, `ctx` yardımcısıyla bir fonksiyon içinde çalışır (sayfadan çıkınca otomatik temizlenir):
`ctx.el` (sarmalayıcı öğe), `ctx.root`, `ctx.route`, `ctx.qs()`, `ctx.qsa()`, `ctx.on(hedef, tür, fn)`, `ctx.onLeave(fn)`, `ctx.setTimeout()`, `ctx.setInterval()`. Hatalar yakalanıp loglanır — sitenizi asla bozmaz.

## Güvenlik

- Yönetim paneli bir şifreyle (`builtin`) ya da **kendi girişinizle** (`auth.mode = 'callback'`) korunur; her API çağrısı oturum + CSRF başlığı ister.
- Yayınlanmış kod, `serve.php` (salt okunur, yalnızca yayınlanmış + aktif) tarafından sunulan veritabanı verisidir.
- Özel kod yalnızca herkese açık sayfalarınızda ve sandbox önizlemede çalışır — yönetim panelinde asla.
- Kodu yalnızca yöneticiler yayınlayabilir ve yayınlanan kod **tüm ziyaretçilerde** çalışır: admin şifresini bir deploy anahtarı gibi koruyun.
- `storage/` (SQLite dosyası + şifre hash'i) web kökünün dışında ya da korumalı olmalı (Apache'de `.htaccess` otomatik oluşturulur).

## Diller

`lang/en.php` referans, `lang/tr.php` Türkçe çeviridir. Config'teki rota ve konum etiketleri `['en' => …, 'tr' => …]` olabilir. Yeni dil eklemek için `en.php`'yi kopyalayıp çevirin ve kodu `I18n::LANGS`'e ekleyin. `php tools/check-i18n.php` dosyaları denetler.

## Yapı

```
src/     Manager (kayıtlar, sürümler, paketler, önizleme), Frontend (iki satırlık entegrasyon), Config, Auth, Db, I18n, Snippets
public/  serve.php (herkese açık salt okunur uç nokta), assets/cc-runtime.js, admin/ (arayüz), index.php + about.php (demo)
lang/    en.php, tr.php       config/  config.example.php       tools/  dil denetleyici
```

## ☕ Projeyi Destekle

Bu proje ücretsiz ve açık kaynaklıdır.

Eğer işini kolaylaştırdıysa, projen için faydalı olduysa veya gelecekteki geliştirmeleri desteklemek istiyorsan bana bir kahve ısmarlayabilirsin. ❤️

[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-Destek%20Ol-orange?style=for-the-badge&logo=buymeacoffee)](https://www.buymeacoffee.com/Gulbaglar)

Açık kaynak geliştirmeyi desteklediğin için teşekkür ederim!

## Geliştirici

**Kahraman Gülbağlar**  
[https://www.gulbaglar.com](https://www.gulbaglar.com)

## Lisans

MIT — bkz. [LICENSE](LICENSE).
