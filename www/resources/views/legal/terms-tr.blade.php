@extends('layouts.legal')

@section('title', 'Kullanım Koşulları')
@section('alternate', route('legal.terms.en'))

@section('content')
    <h1>Kullanım Koşulları</h1>
    <p class="muted">Son güncelleme: 23 Ekim 2026</p>

    <p>
        Bu koşullar, Bahri CANLI tarafından Kale Host markası altında sunulan <strong>Mobile Metrics MCP</strong>
        (<a href="https://metrics.kalehost.net">metrics.kalehost.net</a>) hizmetinin kullanımını düzenler.
        Hizmete giriş yaparak bu koşulları ve <a href="{{ route('legal.privacy') }}">Gizlilik Politikası</a>'nı kabul etmiş olursunuz.
    </p>

    <h2>1. Hizmet</h2>
    <p>
        Mobile Metrics MCP; Google Play, App Store ve AppGallery'deki geliştirici hesaplarınızdan uygulama istatistiklerini
        ve yorumları okuyarak bir panelde ve bağladığınız yapay zekâ asistanları (MCP) aracılığıyla gösterir.
        Hizmet mağaza hesaplarınıza yalnızca okuma amacıyla erişir.
    </p>

    <h2>2. Hesap ve yetki</h2>
    <ul>
        <li>Hizmete Google, Apple veya Huawei hesabınızla giriş yaparsınız; hesabınızın güvenliğinden siz sorumlusunuz.</li>
        <li>Yalnızca erişim yetkiniz olan geliştirici hesaplarını bağlayabilirsiniz. Bir şirket veya takım adına hesap bağlıyorsanız bunun için yetkili olduğunuzu kabul edersiniz.</li>
        <li>Bağladığınız mağazaların (Google, Apple, Huawei) kendi kullanım koşulları ve API politikaları da geçerlidir.</li>
    </ul>

    <h2>3. Kabul edilebilir kullanım</h2>
    <p>Hizmeti hukuka aykırı amaçlarla, başkalarının hesaplarına yetkisiz erişim için ya da hizmetin altyapısına zarar verecek şekilde (ör. aşırı otomatik istek, güvenlik açığı arama) kullanamazsınız.</p>

    <h2>4. Verileriniz</h2>
    <p>
        Bağladığınız hesaplardan çekilen veriler size aittir. Bu verileri yalnızca hizmeti size sunmak için işleriz;
        ayrıntılar <a href="{{ route('legal.privacy') }}">Gizlilik Politikası</a>'ndadır.
    </p>

    <h2>5. Doğruluk ve kesintisiz hizmet</h2>
    <p>
        İstatistikler mağazaların yayınladığı raporlardan alınır; mağazalar veriyi gecikmeli yayınlayabilir, sonradan
        düzeltebilir veya düşük hacimli uygulamalar için hiç sunmayabilir. Hizmet "olduğu gibi" sunulur; verilerin
        eksiksiz, doğru veya kesintisiz olacağı garanti edilmez. Önemli kararlar için mağazanın kendi konsolunu esas alın.
    </p>

    <h2>6. Sorumluluğun sınırlandırılması</h2>
    <p>
        Yürürlükteki hukukun izin verdiği ölçüde, hizmetin kullanımından veya kullanılamamasından doğan dolaylı
        zararlardan, veri veya kâr kaybından sorumlu değiliz.
    </p>

    <h2>7. Fesih</h2>
    <p>
        Bağladığınız mağaza hesaplarını panelden istediğiniz zaman kaldırabilir, hesabınızın silinmesini
        <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a> adresinden isteyebilirsiniz. Bu koşulları ihlal eden
        hesaplar askıya alınabilir veya kapatılabilir.
    </p>

    <h2>8. Değişiklikler</h2>
    <p>Koşullar değişirse bu sayfadaki tarih güncellenir; değişiklikten sonra hizmeti kullanmaya devam etmeniz yeni koşulları kabul ettiğiniz anlamına gelir.</p>

    <h2>9. Uygulanacak hukuk</h2>
    <p>Bu koşullar Türkiye Cumhuriyeti hukukuna tabidir.</p>

    <h2>10. İletişim</h2>
    <p><a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a></p>
@endsection
