@extends('layouts.legal')

@section('title', 'Gizlilik Politikası')
@section('alternate', route('legal.privacy.en'))

@section('content')
    <h1>Gizlilik Politikası</h1>
    <p class="muted">Son güncelleme: 23 Ekim 2026</p>

    <p>
        Bu politika, <strong>Mobile Metrics MCP</strong> (<a href="https://metrics.kalehost.net">metrics.kalehost.net</a>)
        hizmetinin hangi kişisel verileri neden işlediğini açıklar. Mobile Metrics MCP, uygulama geliştiricilerinin
        Google Play, App Store ve AppGallery'deki uygulama istatistiklerini tek yerde görmesini ve bunları yapay zekâ
        asistanlarıyla analiz etmesini sağlayan bir web hizmetidir.
    </p>

    <h2>1. Veri sorumlusu</h2>
    <p>
        Veri sorumlusu Bahri CANLI'dır (bireysel geliştirici, Kale Host markası, Türkiye).
        İletişim: <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a>
    </p>

    <h2>2. İşlediğimiz veriler</h2>
    <ul>
        <li><strong>Hesap bilgileri:</strong> Google, Apple veya Huawei ile giriş yaptığınızda bu sağlayıcının bize verdiği ad, e-posta adresi, profil fotoğrafı ve hesap kimliği. Parola saklamayız.</li>
        <li><strong>Mağaza bağlantı bilgileri:</strong> Bağladığınız geliştirici hesapları için OAuth yenileme anahtarları (refresh token) ve sizin yüklediğiniz API anahtarları (ör. App Store Connect .p8 anahtarı, Huawei Service Account dosyası, Google service account dosyası).</li>
        <li><strong>Mağaza verileri:</strong> Bağladığınız hesaplardaki uygulama listesi, günlük istatistikler (indirme, kurulum, kaldırma, sayfa görüntüleme, puan, çökme oranları) ve kullanıcı yorumları. Yorumlar, mağazanın paylaştığı ölçüde yorum yazanın takma adını ve ülkesini içerebilir.</li>
        <li><strong>Teknik veriler:</strong> Oturum çerezleri, IP adresi ve sunucu kayıtları (hata ayıklama ve güvenlik için).</li>
    </ul>

    <h2>3. Verileri neden işliyoruz</h2>
    <ul>
        <li>Hesabınızı oluşturmak ve sizi tanımak,</li>
        <li>bağladığınız mağaza hesaplarından istatistik ve yorumları sizin adınıza düzenli olarak çekmek,</li>
        <li>bu verileri size panelde ve sizin bağladığınız yapay zekâ asistanları (MCP) aracılığıyla göstermek,</li>
        <li>hizmetin güvenliğini ve çalışmasını sağlamak.</li>
    </ul>
    <p>Hukuki dayanak, hizmetin sunulması için gerekli olması (sözleşmenin ifası) ve hizmetin güvenliğine yönelik meşru menfaattir.</p>

    <h2>4. Mağaza erişimi yalnızca okuma amaçlıdır</h2>
    <p>
        Mobile Metrics MCP mağaza hesaplarınıza yalnızca istatistik ve yorumları okumak için erişir. Uygulama yayınlamaz,
        mağaza ayarlarını değiştirmez ve yorumlara sizin adınıza yanıt vermez.
    </p>

    <h2>5. Google kullanıcı verileri</h2>
    <p>
        Mobile Metrics MCP'in Google API'lerinden aldığı bilgileri kullanması ve başka bir uygulamaya aktarması,
        Sınırlı Kullanım (Limited Use) koşulları dahil olmak üzere
        <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Hizmetleri Kullanıcı Verileri Politikası</a>'na
        uygundur. Google Play verileri yalnızca size istatistiklerinizi göstermek için kullanılır; reklam amacıyla
        kullanılmaz, satılmaz ve insanlar tarafından okunmaz (güvenlik, yasal zorunluluk veya sizin açık izniniz hariç).
    </p>

    <h2>6. Paylaşım</h2>
    <p>Verilerinizi satmayız ve reklam için kullanmayız. Veriler yalnızca şu durumlarda üçüncü taraflara ulaşır:</p>
    <ul>
        <li><strong>Mağazalar:</strong> Verileri çekmek için Google, Apple ve Huawei API'lerine sizin yetkinizle istek gönderilir.</li>
        <li><strong>Altyapı:</strong> Site, Cloudflare üzerinden yayınlanır; trafik Cloudflare ağından geçer. Sunucu Kale Host altyapısındadır.</li>
        <li><strong>Sizin bağladığınız yapay zekâ asistanları:</strong> MCP ile bir asistanı (ör. Claude) bağladığınızda, asistanın sorduğu veriler sizin talebinizle o asistana gönderilir. Bu verilerin orada işlenmesi ilgili asistanın politikasına tabidir.</li>
        <li><strong>Yasal zorunluluk:</strong> Yetkili makamların hukuka uygun talepleri.</li>
    </ul>

    <h2>7. Saklama ve güvenlik</h2>
    <ul>
        <li>Anahtarlar ve token'lar veritabanında şifreli saklanır; panelde tekrar gösterilmez.</li>
        <li>Verileriniz hesabınız silinene kadar saklanır. Bir mağaza hesabını panelden sildiğinizde, o hesabın anahtarları ve ondan çekilen veriler de silinir.</li>
        <li>Sunucu kayıtları kısa süreli tutulur ve düzenli olarak temizlenir.</li>
    </ul>

    <h2>8. Haklarınız</h2>
    <p>
        6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) ve uygulanabildiği ölçüde GDPR kapsamında verilerinize
        erişme, düzeltme, silme ve işlemeye itiraz etme haklarına sahipsiniz. Talepleriniz için
        <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a> adresine yazabilirsiniz. Erişim izinlerini ayrıca
        <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">Google</a>,
        Apple ve Huawei hesap ayarlarınızdan da geri alabilirsiniz.
    </p>

    <h2>9. Çerezler</h2>
    <p>Yalnızca oturumunuzu ve güvenliği (CSRF koruması) sağlayan zorunlu çerezler kullanılır. Analiz veya reklam çerezi yoktur.</p>

    <h2>10. Çocuklar</h2>
    <p>Hizmet, uygulama geliştiricilerine yöneliktir; 18 yaş altındaki kişilere yönelik değildir.</p>

    <h2>11. Değişiklikler</h2>
    <p>Bu politika değişirse bu sayfadaki tarih güncellenir. Önemli değişiklikler panelde duyurulur.</p>
@endsection
