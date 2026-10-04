/* ============================================================
   Sutera Care Provider — language switcher (EN / BM / 中文)

   English lives in index.html and is snapshotted on load, so it
   is never duplicated here. Only the translations live below.

   To add a string: give the element a data-i18n="some.key"
   attribute in index.html (data-i18n-ph for a placeholder,
   data-i18n-label for an aria-label), then add "some.key" to
   ms and zh. Values are HTML — inline <strong>/<a>/<br> are fine.
   A key missing from a translation simply shows in English.
   ============================================================ */

(function () {
  'use strict';

  var STORAGE_KEY = 'sutera-provider-lang';
  var DEFAULT_LANG = 'en';

  /* The html lang attribute written for each language. */
  var HTML_LANG = { en: 'en', ms: 'ms', zh: 'zh-Hans' };

  /* ---------------------------------------------------------
     Bahasa Malaysia
     --------------------------------------------------------- */
  var ms = {
    "meta.title": "Sutera Care Provider — Penjaga terlatih untuk penjagaan di rumah dan warga emas di Malaysia",
    "meta.desc": "Sutera Care Provider menempatkan penjaga terlatih dan disaring di rumah-rumah di Malaysia: penjagaan diri harian, bantuan pergerakan dan kebersihan, iringan ke temu janji, serta lawatan urutan dan kesejahteraan. Penjaga, bukan jururawat atau doktor.",
    "meta.ogTitle": "Sutera Care Provider — Penjaga terlatih untuk penjagaan di rumah dan warga emas",
    "meta.ogDesc": "Penjaga yang disaring untuk warga emas dan pesakit di rumah di Malaysia. Penjagaan diri harian, iringan ke temu janji, urutan dan kesejahteraan.",
    "header.span2": "Penjaga terlatih untuk penjagaan di rumah dan warga emas",
    "header.span3": "Talian penjagaan · 8 pagi–10 malam setiap hari",
    "header.span4": "Log masuk",
    "header.a1": "Dapatkan Sebut Harga",
    "header.span5": "Menu",
    "header.a2": "Perkhidmatan",
    "header.a3": "Cara ia berfungsi",
    "header.a4": "Keadaan kesihatan",
    "header.a5": "Mengapa kami",
    "header.a6": "Penjaga kami",
    "header.a7": "Kerjaya",
    "header.a8": "Panduan",
    "header.a9": "Soal Jawab",
    "header.a10": "Hubungi",
    "hero.p1": "Penjagaan di rumah · Penjagaan warga emas · Kesejahteraan",
    "hero.h11": "Penjaga terlatih, di rumah anda, mengikut jadual yang anda tetapkan.",
    "hero.p2": "Sutera Care Provider menempatkan penjaga yang telah disaring dengan keluarga di seluruh Klang Valley — untuk ibu bapa yang sudah berusia, untuk sesiapa yang sedang pulih di rumah, dan untuk sesiapa yang memerlukan bantuan yang tetap sepanjang hari. Anda mendapat penjaga yang sama, pelan penjagaan bertulis, dan rekod bagi setiap lawatan.",
    "hero.a1": "Dapatkan sebut harga",
    "hero.a2": "Lihat tugas penjaga",
    "hero.p3": "Mencari kerja sebagai penjaga? <a href=\"#careers\">Lihat jawatan penjaga&nbsp;→</a>",
    "included.h21": "Apa yang termasuk dalam penempatan",
    "included.p1": "Enam perkara yang sama setiap kali, sama ada anda menempah tiga jam seminggu atau penjaga tinggal bersama.",
    "included.li1": "<strong>Penjaga yang dipadankan</strong>Dipilih mengikut bahasa yang dituturkan di rumah dan jenis bantuan yang diperlukan.",
    "included.li2": "<strong>Pelan penjagaan bertulis</strong>Dipersetujui bersama keluarga sebelum lawatan pertama, supaya tiada apa yang diandaikan.",
    "included.li3": "<strong>Daftar masuk dan daftar keluar</strong>Direkodkan pada setiap lawatan, supaya kehadiran tidak bergantung pada ingatan.",
    "included.li4": "<strong>Catatan lawatan pada hari yang sama</strong>Ringkasan tentang perjalanan hari itu, boleh dibaca oleh ahli keluarga yang anda namakan.",
    "included.li5": "<strong>Penyelaras yang dinamakan</strong>Seorang yang mengenali kes anda dan menjawab panggilan.",
    "included.li6": "<strong>Penjaga ganti apabila perlu</strong>Penjaga ganti yang sudah memegang pelan penjagaan, jika penjaga anda tidak sihat.",
    "banner.div1": "<strong>Kami menyediakan penjaga — bukan jururawat atau doktor.</strong>&ensp;Pasukan kami membantu urusan harian, kebersihan diri, pergerakan dan menemani. Rawatan klinikal kekal di tangan doktor anda sendiri atau penyedia kejururawatan di rumah yang berlesen.",
    "services.h21": "Apa yang dilakukan oleh penjaga kami",
    "services.p1": "Penjagaan diatur mengikut jam, mengikut hari, atau penjaga tinggal bersama. Setiap tugas di bawah dipersetujui dalam pelan penjagaan sebelum penjaga bermula, supaya tiada sesiapa yang meneka apa yang termasuk.",
    "services.h31": "Penjagaan diri harian",
    "services.p2": "Bantuan harian yang memastikan seseorang selesa, bersih dan selamat di rumah.",
    "services.li1": "Memandikan, mandi pancuran dan mandi lap",
    "services.li2": "Menukar lampin dan pad dewasa",
    "services.li3": "Berpakaian, berdandan dan penjagaan mulut",
    "services.li4": "Bantuan ke tandas dan kawalan kencing",
    "services.li5": "Mengubah posisi badan dan mencegah kudis tekanan",
    "services.li6": "Bantuan makan dan penyediaan makanan",
    "services.h32": "Pergerakan &amp; teman",
    "services.p3": "Sokongan yang memastikan seseorang terus bergerak, sedar akan sekeliling dan terlibat dalam hari-harinya.",
    "services.li7": "Bantuan berpindah, berjalan dan berkerusi roda",
    "services.li8": "Iringan ke temu janji hospital dan klinik",
    "services.li9": "Senaman ringan dan regangan yang ditetapkan",
    "services.li10": "Peringatan ubat pada waktu yang ditetapkan",
    "services.li11": "Berbual, membaca dan menemani",
    "services.li12": "Kerja rumah ringan di sekitar orang yang dijaga",
    "services.h33": "Urutan &amp; kesejahteraan",
    "services.p4": "Ditempah sebagai sesi tunggal atau ditambah pada jadual penjagaan sedia ada.",
    "services.li13": "Urutan relaksasi dan keselesaan",
    "services.li14": "Urutan selepas bersalin dan sokongan berpantang",
    "services.li15": "Urutan anggota badan yang lembut untuk klien yang terlantar",
    "services.li16": "Regangan berbantu untuk sendi yang kaku",
    "services.li17": "Tuam panas dan rutin keselesaan",
    "services.li18": "Lawatan tunggal atau slot mingguan tetap",
    "services.p5": "Perlukan sesuatu yang tiada dalam senarai? Nyatakan dalam borang sebut harga — jika ia di luar skop tugas penjaga, kami akan memberitahu anda dengan jelas dan merujuk anda kepada penyedia yang sesuai.",
    "conditions.h21": "Penjagaan bagi keadaan kesihatan yang biasa",
    "conditions.p1": "Kebanyakan keluarga datang kepada kami kerana sesuatu diagnosis, jatuh, atau keluar dari hospital. Penjaga kami tidak merawat penyakit itu — itu tugas doktor anda — tetapi mereka faham bagaimana kehidupan harian dengannya, dan pelan penjagaan ditulis berdasarkannya.",
    "conditions.h31": "Demensia &amp; hilang ingatan",
    "conditions.p2": "Wajah yang dikenali dan rutin yang tetap. Peringatan lembut untuk makan, mandi dan berpakaian, pengawasan supaya tidak merayau, dan kesabaran dengan soalan yang berulang.",
    "conditions.h32": "Selepas strok",
    "conditions.p3": "Pemindahan yang selamat, bantuan bagi kelemahan sebelah badan, senaman di rumah yang ditetapkan oleh ahli terapi anda, dan iringan ke temu janji pemulihan.",
    "conditions.h33": "Pulang dari hospital",
    "conditions.p4": "Minggu-minggu pertama selepas pembedahan atau kemasukan wad: bantuan untuk bangun dan bergerak, penjagaan diri, makanan, dan seseorang yang perasan apabila ada sesuatu yang tidak kena.",
    "conditions.h34": "Parkinson &amp; kelemahan usia",
    "conditions.p5": "Sokongan untuk berjalan dan keseimbangan, pencegahan jatuh di sekitar rumah, dan bantuan tanpa tergesa-gesa untuk makan, berpakaian dan mandi.",
    "conditions.h35": "Penjagaan pesakit terlantar",
    "conditions.p6": "Mengubah posisi badan secara berkala, mandi di atas katil, menukar lampin, memeriksa kulit di kawasan tekanan, dan urutan anggota badan untuk keselesaan.",
    "conditions.h36": "Paliatif &amp; akhir hayat",
    "conditions.p7": "Keselesaan, maruah dan teman bersama pasukan paliatif, serta peluang berehat untuk ahli keluarga yang memerlukannya.",
    "how.h21": "Cara ia berfungsi",
    "how.p1": "Dari panggilan pertama hingga lawatan pertama biasanya mengambil masa tiga hingga lima hari bekerja — lebih cepat jika mendesak.",
    "how.p2": "Penilaian di rumah ialah bahagian yang menurut keluarga paling penting. Kami bertemu orang yang akan dijaga, melihat rumahnya, dan menulis pelan penjagaan bersama anda, bukannya memberikan pakej standard.",
    "how.h31": "Ceritakan situasi anda",
    "how.p3": "Panggilan ringkas atau borang sebut harga. Siapa yang memerlukan penjagaan, apa bantuan yang diperlukan, dan bila.",
    "how.h32": "Penilaian di rumah",
    "how.p4": "Kami melawat, bertemu orang yang dijaga dan keluarganya, dan menulis pelan penjagaan bersama — tugas, waktu, peraturan rumah.",
    "how.h33": "Penjaga yang dipadankan",
    "how.p5": "Kami mencadangkan penjaga yang dipadankan mengikut bahasa, pengalaman dan jadual. Anda bertemu mereka sebelum membuat pengesahan.",
    "how.h34": "Penjagaan bermula",
    "how.p6": "Lawatan bermula mengikut jadual yang dipersetujui. Anda dapat melihat daftar masuk dan catatan lawatan, dan penyelaras anda sentiasa boleh dihubungi.",
    "why.h21": "Mengapa keluarga memilih kami",
    "why.div1": "<strong>Setiap penjaga disaring sebelum ditempatkan.</strong> Identiti dan kelayakan untuk bekerja disemak, rujukan diperoleh, dan semakan rekod polis disimpan dalam fail.",
    "why.div2": "<strong>Penjaga yang sama, bukan orang asing yang silih berganti.</strong> Kesinambungan paling penting untuk penjagaan demensia dan akhir hayat. Anda mendapat seorang penjaga yang dinamakan dan seorang penjaga ganti yang dinamakan.",
    "why.div3": "<strong>Pelan penjagaan bertulis, dipersetujui bersama anda.</strong> Setiap tugas dalam skop disenaraikan. Tiada apa yang diandaikan, dan sebarang perubahan dipersetujui, bukan sekadar ditambah begitu sahaja.",
    "why.div4": "<strong>Anda boleh lihat apa yang berlaku.</strong> Waktu daftar masuk dan daftar keluar serta catatan lawatan direkodkan, dan keluarga boleh membacanya pada hari yang sama.",
    "why.div5": "<strong>Bahasa yang dituturkan di rumah.</strong> Bahasa Malaysia, Inggeris, Mandarin, Kantonis dan Tamil dalam pasukan kami — kami memadankan mengikut bahasa yang paling selesa bagi orang yang dijaga.",
    "why.div6": "<strong>Garis yang jelas tentang apa yang kami tidak lakukan.</strong> Kami tidak memberi suntikan, membalut luka, mengendalikan titisan IV atau menukar kateter. Kejujuran inilah yang menjadikan perkara lain boleh dipercayai.",
    "team.h21": "Kenali penjaga kami",
    "team.p1": "Keluarga mahu tahu siapa yang akan masuk ke rumah mereka. Setiap penjaga di bawah telah ditemu duga secara bersemuka dan lulus semakan rujukan serta rekod polis — dan anda bertemu penjaga anda sebelum penjagaan disahkan.",
    "team.p2": "Penjagaan warga emas",
    "team.h31": "Profil akan datang",
    "team.p3": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "team.p4": "Penjaga tinggal bersama",
    "team.h32": "Profil akan datang",
    "team.p5": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "team.p6": "Penjagaan demensia",
    "team.h33": "Profil akan datang",
    "team.p7": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "team.p8": "Pulang dari hospital",
    "team.h34": "Profil akan datang",
    "team.p9": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "team.p10": "Urutan &amp; kesejahteraan",
    "team.h35": "Profil akan datang",
    "team.p11": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "team.p12": "Penyelaras penjagaan",
    "team.h36": "Profil akan datang",
    "team.p13": "Kami sedang menambah pengenalan daripada pasukan kami. Tanya penyelaras anda tentang penjaga yang tersedia di kawasan anda.",
    "quote.h21": "Minta sebut harga",
    "quote.p1": "<strong>Terima kasih.</strong> Kami telah menerima pertanyaan anda dan akan membalas dalam masa satu hari bekerja.",
    "quote.p2": "Orang yang mengatur penjagaan selalunya bukan orang yang menerimanya, jadi kami bertanya tentang kedua-duanya. Tiada apa di sini yang mengikat anda. Sebut harga anda diperincikan mengikut situasi anda — jam, tahap bantuan dan lokasi — supaya tiada caj tambahan kemudian.",
    "quote.h31": "Pertanyaan penjagaan",
    "quote.p3": "Kami membalas dalam masa satu hari bekerja.",
    "quote.legend1": "Butiran anda",
    "quote.label1": "Nama anda *",
    "quote.label2": "Telefon / WhatsApp *",
    "quote.label3": "E-mel *",
    "quote.label4": "Hubungan anda dengan orang yang memerlukan penjagaan",
    "quote.option1": "— Pilih —",
    "quote.option2": "Anak lelaki atau perempuan",
    "quote.option3": "Suami/isteri atau pasangan",
    "quote.option4": "Ahli keluarga lain",
    "quote.option5": "Kawan atau jiran",
    "quote.option6": "Saya sendiri yang memerlukan penjagaan",
    "quote.option7": "Rujukan hospital atau klinik",
    "quote.legend2": "Siapa yang memerlukan penjagaan",
    "quote.label5": "Nama mereka *",
    "quote.label6": "Umur",
    "quote.label7": "Kawasan / bandar *",
    "quote.label8": "Pergerakan",
    "quote.option8": "— Pilih —",
    "quote.option9": "Berjalan sendiri",
    "quote.option10": "Berjalan dengan tongkat atau walker",
    "quote.option11": "Pengguna kerusi roda",
    "quote.option12": "Kebanyakan masa terlantar",
    "quote.label9": "Apa bantuan yang diperlukan?",
    "quote.label10": "Hari dan waktu yang anda fikirkan",
    "quote.button1": "Hantar pertanyaan",
    "quote.p4": "Kami menggunakan butiran ini hanya untuk menyediakan sebut harga dan mengatur penilaian. Kami tidak menjual atau berkongsinya. Lihat <a href=\"privacy.html\">notis privasi</a> kami.",
    "quote.h32": "Apa yang berlaku seterusnya",
    "quote.li1": "<strong>Dalam masa satu hari bekerja</strong>Penyelaras akan menelefon atau menghantar WhatsApp kepada anda untuk memahami situasi.",
    "quote.li2": "<strong>Penilaian di rumah</strong>Kami melawat, bertemu orang yang dijaga, dan menulis pelan penjagaan bersama anda.",
    "quote.li3": "<strong>Sebut harga terperinci</strong>Jam, tahap bantuan dan sebarang caj tambahan, ditunjukkan satu demi satu.",
    "quote.li4": "<strong>Bertemu penjaga anda</strong>Anda bertemu penjaga yang dicadangkan sebelum mengesahkan apa-apa.",
    "quote.p5": "Lebih suka berbincang terus?<br><a href=\"#contact\">Telefon atau WhatsApp talian penjagaan&nbsp;→</a>",
    "careers.p1": "Kerjaya",
    "careers.h21": "Kerja penjaga yang sesuai dengan kehidupan anda",
    "careers.p2": "Kami sedang mengambil penjaga di seluruh Klang Valley — sepenuh masa, separuh masa, tinggal bersama dan hujung minggu. Yang paling penting ialah sikap yang betul: sabar, boleh diharap dan menghormati orang yang anda jaga. Selebihnya kami akan bantu.",
    "careers.p3": "<strong>Terima kasih kerana memohon.</strong> Kami akan menghubungi pemohon yang disenarai pendek dalam masa seminggu.",
    "careers.h31": "Mengapa penjaga bekerja dengan kami",
    "careers.h41": "Pilih cara anda bekerja",
    "careers.p4": "Sepenuh masa, separuh masa, tinggal bersama atau hujung minggu sahaja. Beritahu kami waktu anda dan kami padankan penempatan dengannya.",
    "careers.h42": "Bekerja dekat dengan rumah",
    "careers.p5": "Penempatan dipadankan dengan kawasan yang anda nyatakan boleh bekerja, dan dengan bahasa yang anda tuturkan.",
    "careers.h43": "Latihan dan kemajuan kerjaya",
    "careers.p6": "Orientasi tentang standard penjagaan kami sebelum penempatan pertama anda, dan peluang untuk maju ke kes khusus seperti demensia dan penjagaan tinggal bersama.",
    "careers.h44": "Semuanya dalam satu aplikasi",
    "careers.p7": "Lihat jadual dan pelan penjagaan anda, daftar masuk dan keluar, dan tulis catatan lawatan melalui aplikasi penjaga Sutera.",
    "careers.h45": "Penyelaras yang menyokong anda",
    "careers.p8": "Seorang yang dinamakan yang mengenali kes anda, menyelesaikan masalah dengan keluarga, dan mengatur penjaga ganti apabila anda tidak sihat.",
    "careers.h46": "Skop kerja yang jelas",
    "careers.p9": "Anda tidak akan diminta melakukan kerja jururawat. Setiap tugas ada dalam pelan penjagaan, dipersetujui bersama keluarga sebelum anda bermula.",
    "careers.h32": "Apa yang akan anda lakukan",
    "careers.li1": "Memandikan, mandi pancuran, mandi di atas katil dan menukar lampin",
    "careers.li2": "Berpakaian, berdandan dan penjagaan mulut",
    "careers.li3": "Pemindahan yang selamat, bantuan berjalan dan berkerusi roda",
    "careers.li4": "Menyediakan makanan dan membantu makan",
    "careers.li5": "Peringatan ubat pada waktu yang ditetapkan",
    "careers.li6": "Iringan ke temu janji hospital dan klinik",
    "careers.li7": "Senaman ringan, berbual dan menemani",
    "careers.li8": "Kerja rumah ringan di sekitar orang yang dijaga",
    "careers.h33": "Siapa yang kami cari",
    "careers.li9": "Sabar, baik hati dan boleh diharap — bahagian yang paling penting",
    "careers.li10": "Layak dari segi undang-undang untuk bekerja di Malaysia",
    "careers.li11": "Selesa melakukan tugas penjagaan diri",
    "careers.li12": "Bertutur sekurang-kurangnya satu daripada Bahasa Malaysia, Inggeris, Mandarin, Kantonis atau Tamil",
    "careers.li13": "Pengalaman atau sijil penjagaan dialu-alukan, tetapi tidak wajib",
    "careers.li14": "Bersedia menjalani semakan rujukan dan rekod polis",
    "careers.h34": "Proses pengambilan",
    "careers.h35": "Mohon",
    "careers.p10": "Hantar borang di bawah. Ia mengambil masa kira-kira tiga minit.",
    "careers.h36": "Temu duga",
    "careers.p11": "Pemohon yang disenarai pendek akan dihubungi dalam masa seminggu dan ditemu duga secara bersemuka.",
    "careers.h37": "Semakan &amp; orientasi",
    "careers.p12": "Kami mendapatkan rujukan, melengkapkan semakan rekod polis, dan menerangkan standard penjagaan serta aplikasi kami kepada anda.",
    "careers.h38": "Penempatan pertama",
    "careers.p13": "Kami memadankan anda dengan keluarga mengikut kawasan, bahasa dan jadual, dan memperkenalkan anda sebelum penjagaan bermula.",
    "careers.h39": "Mohon untuk bekerja dengan kami",
    "careers.p14": "Jawatan kosong: penjaga warga emas, penjaga di rumah dan penjaga tinggal bersama, ahli terapi urutan &amp; kesejahteraan, serta penyelaras penjagaan.",
    "careers.legend1": "Butiran pemohon",
    "careers.label1": "Nama penuh *",
    "careers.label2": "Telefon / WhatsApp *",
    "careers.label3": "E-mel",
    "careers.label4": "Kawasan anda boleh bekerja *",
    "careers.legend2": "Jawatan dan pengalaman",
    "careers.label5": "Jawatan yang dipohon *",
    "careers.option1": "— Pilih —",
    "careers.option2": "Penjaga — penjagaan warga emas",
    "careers.option3": "Penjaga — penjagaan di rumah",
    "careers.option4": "Penjaga tinggal bersama",
    "careers.option5": "Ahli terapi urutan &amp; kesejahteraan",
    "careers.option6": "Penyelaras penjagaan (pejabat)",
    "careers.option7": "Lain-lain",
    "careers.label6": "Tahun pengalaman",
    "careers.label7": "Ketersediaan",
    "careers.option8": "— Pilih —",
    "careers.option9": "Sepenuh masa",
    "careers.option10": "Separuh masa",
    "careers.option11": "Tinggal bersama",
    "careers.option12": "Hujung minggu sahaja",
    "careers.label8": "Bahasa yang dituturkan",
    "careers.label9": "Latihan atau sijil",
    "careers.button1": "Hantar permohonan",
    "careers.p15": "Pemohon yang disenarai pendek akan ditemu duga secara bersemuka. Penempatan tertakluk kepada semakan rujukan dan rekod polis.",
    "careers.h310": "Soalan lazim pemohon",
    "careers.summary1": "Saya tiada pengalaman sebagai penjaga. Bolehkah saya memohon?",
    "careers.p16": "Boleh. Ceritakan apa-apa pengalaman yang berkaitan — menjaga ahli keluarga juga dikira. Penjaga baharu bermula dengan kes yang lebih mudah.",
    "careers.summary2": "Bolehkah saya memilih waktu kerja?",
    "careers.p17": "Boleh. Beritahu kami sama ada anda mahu sepenuh masa, separuh masa, tinggal bersama atau hujung minggu, dan penempatan akan dipadankan dengannya.",
    "careers.summary3": "Di manakah tempat kerjanya?",
    "careers.p18": "Di rumah klien sendiri di seluruh Klang Valley. Kami memadankan anda dengan kawasan yang anda nyatakan boleh bekerja, supaya perjalanan tidak terlalu jauh.",
    "careers.summary4": "Bagaimana saya mendapat jadual saya?",
    "careers.p19": "Melalui aplikasi penjaga Sutera: syif anda, pelan penjagaan bagi setiap klien, daftar masuk dan daftar keluar, serta catatan lawatan.",
    "careers.summary5": "Adakah anda mengambil jururawat?",
    "careers.p20": "Bukan untuk kerja kejururawatan. Kami menempatkan penjaga untuk sokongan kehidupan harian, bukan penjagaan klinikal. Jururawat dialu-alukan memohon sebagai penjaga jika itu kerja yang mereka mahukan.",
    "guides.h21": "Panduan untuk keluarga",
    "guides.p1": "Nasihat mudah untuk keputusan yang dihadapi keluarga apabila seseorang di rumah mula memerlukan bantuan. Percuma dibaca, tanpa pendaftaran.",
    "guides.span1": "Memilih penjagaan",
    "guides.h31": "Penjaga, pembantu rumah atau rumah jagaan?",
    "guides.p2": "Apa yang sebenarnya terlibat dalam setiap pilihan, untuk siapa ia sesuai, dan soalan yang perlu ditanya sebelum anda membuat keputusan.",
    "guides.span2": "Baca panduan&nbsp;→",
    "guides.span3": "Keluar dari hospital",
    "guides.h32": "Pulang dari hospital: senarai semak",
    "guides.p3": "Apa yang perlu diuruskan sebelum hari keluar wad, dan cara melalui dua minggu pertama di rumah.",
    "guides.span4": "Baca panduan&nbsp;→",
    "guides.span5": "Keselamatan di rumah",
    "guides.h33": "Mencegah jatuh di rumah",
    "guides.p4": "Senarai semak bilik demi bilik untuk perubahan yang paling penting, kebanyakannya murah atau percuma.",
    "guides.span6": "Baca panduan&nbsp;→",
    "faq.h21": "Soalan lazim keluarga",
    "faq.summary1": "Adakah penjaga anda jururawat?",
    "faq.p1": "Tidak, dan kami sangat berhati-hati tentang perkara ini. Penjaga kami membantu urusan kehidupan harian — memandikan, menukar lampin, menyuap makan, pergerakan, menemani dan mengiringi ke temu janji. Mereka tidak memberi suntikan, membalut luka, mengendalikan titisan IV, memasang atau menukar kateter, atau membuat sebarang keputusan klinikal. Jika situasi anda memerlukannya, anda memerlukan penyedia kejururawatan di rumah yang berlesen, dan kami akan memberitahu anda dengan jujur dan tidak akan menerima tempahan itu.",
    "faq.summary2": "Bolehkah penjaga memberi ubat?",
    "faq.p2": "Mereka boleh memberi peringatan pada waktu yang ditetapkan, menghulurkan dos yang telah diasingkan, dan merekodkan bahawa ubat telah diambil. Mereka tidak mengubah dos atau memberi apa-apa melalui suntikan. Ubat hendaklah diasingkan ke dalam kotak ubat mingguan oleh keluarga atau ahli farmasi.",
    "faq.summary3": "Bagaimana jika kami tidak serasi dengan penjaga?",
    "faq.p3": "Beritahu penyelaras anda dan kami akan menukar penempatan. Ketidakserasian ialah perkara biasa dalam penjagaan di rumah, dan lebih baik dibangkitkan pada minggu pertama daripada dipendam.",
    "faq.summary4": "Apa yang berlaku jika penjaga kami sakit?",
    "faq.p4": "Penyelaras anda akan mengatur penjaga ganti yang sudah memegang pelan penjagaan. Bagi penempatan tinggal bersama, seorang penjaga ganti yang dinamakan dipersetujui sebelum penjagaan bermula.",
    "faq.summary5": "Adakah anda menyediakan penjagaan di luar Klang Valley?",
    "faq.p5": "Kami bermula di Klang Valley. Bagi kawasan lain, kami menerima pertanyaan anda dan memberitahu dengan jujur sama ada kami boleh menyediakan penjaga, bukannya berjanji tetapi gagal menunaikannya.",
    "faq.summary6": "Bagaimana keluarga sentiasa dimaklumkan?",
    "faq.p6": "Penjaga membuat daftar masuk dan daftar keluar bagi setiap lawatan dan menulis catatan lawatan yang ringkas. Ahli keluarga yang diberi akses boleh membacanya pada hari yang sama, bersama jadual untuk minggu hadapan.",
    "faq.summary7": "Siapa yang boleh melihat maklumat ahli keluarga saya?",
    "faq.p7": "Hanya penjaga yang ditugaskan, penyelaras anda, dan ahli keluarga yang anda namakan. Setiap akses direkodkan. Kami menganggap maklumat kesihatan sebagai data peribadi sensitif di bawah Akta Perlindungan Data Peribadi 2010 Malaysia.",
    "faq.summary8": "Adakah Sutera Care Provider sama dengan Sutera Cares?",
    "faq.p8": "Kedua-duanya berkaitan tetapi berasingan. Sutera Cares ialah dana perubatan bukan untung untuk pelarian di Malaysia. Sutera Care Provider ialah syarikat penjagaan di rumah komersial. Bayaran klien di sini tidak membiayai badan amal itu secara automatik, dan derma kepada badan amal tidak pernah menampung perniagaan ini — kedua-duanya menyimpan akaun yang berasingan.",
    "contact.h21": "Hubungi kami",
    "contact.h31": "Talian penjagaan",
    "contact.p1": "8 pagi–10 malam setiap hari · BM, Inggeris, Mandarin, Tamil",
    "contact.h32": "E-mel",
    "contact.p2": "Pertanyaan, sebut harga dan permohonan.",
    "contact.h33": "Kawasan kami",
    "contact.p3": "Negeri lain melalui perbincangan.",
    "sister.p1": "<strong>Organisasi saudara kami yang bukan untung.</strong> Sutera Cares ialah dana perubatan pimpinan komuniti untuk pelarian di Malaysia — kelahiran selamat, penjagaan bayi baru lahir dan kecemasan perubatan. Organisasi berasingan, akaun berasingan. <a href=\"https://www.suteracares.org/\">Lawati Sutera Cares&nbsp;→</a>",
    "footer.p1": "Sutera Care Provider — penjaga terlatih untuk penjagaan di rumah dan warga emas di Malaysia.",
    "footer.p2": "Sutera Care Provider membekalkan penjaga bukan perubatan. Kami tidak menyediakan doktor, jururawat, atau sebarang rawatan klinikal atau perubatan, dan tiada apa di laman ini yang merupakan nasihat perubatan. Maklumat peribadi dan kesihatan dikendalikan sebagai data peribadi sensitif di bawah Akta Perlindungan Data Peribadi 2010.",
    "footer.p3": "<a href=\"/portal/\">Portal kakitangan, penjaga dan keluarga</a><a href=\"#careers\">Kerjaya</a><a href=\"#guides\">Panduan</a><a href=\"privacy.html\">Notis privasi</a>",
    "wa.span1": "WhatsApp kami",
    "header.label1": "Sutera Care Provider — laman utama",
    "header.label2": "Pilih bahasa",
    "header.label3": "Utama",
    "quote.ph1": "Contohnya: mandi dan tukar lampin setiap pagi, iringan ke temu janji hospital setiap dua minggu, teman pada waktu petang.",
    "quote.ph2": "cth. Isn–Jum, 9 pagi–5 petang",
    "careers.ph1": "cth. Bahasa Malaysia, Inggeris, Mandarin",
    "careers.ph2": "Kursus penjaga, pertolongan cemas, majikan terdahulu, atau apa-apa lagi yang perlu kami tahu.",
    "wa.label1": "Berbual dengan kami di WhatsApp"
  };

  /* ---------------------------------------------------------
     简体中文
     --------------------------------------------------------- */
  var zh = {
    "meta.title": "Sutera Care Provider — 马来西亚居家及乐龄护理的专业看护员",
    "meta.desc": "Sutera Care Provider 为马来西亚家庭安排经过培训和审查的看护员：日常个人护理、行动与卫生协助、陪同复诊，以及按摩与保健服务。我们提供的是看护员，而不是护士或医生。",
    "meta.ogTitle": "Sutera Care Provider — 居家及乐龄护理的专业看护员",
    "meta.ogDesc": "为马来西亚的乐龄人士及居家病患提供经过审查的看护员。日常个人护理、陪同复诊、按摩与保健。",
    "header.span2": "居家及乐龄护理的专业看护员",
    "header.span3": "护理热线 · 每日早上8时至晚上10时",
    "header.span4": "登入",
    "header.a1": "索取报价",
    "header.span5": "选单",
    "header.a2": "服务",
    "header.a3": "服务流程",
    "header.a4": "常见病况",
    "header.a5": "选择我们",
    "header.a6": "我们的看护员",
    "header.a7": "招聘",
    "header.a8": "指南",
    "header.a9": "常见问题",
    "header.a10": "联络我们",
    "hero.p1": "居家护理 · 乐龄护理 · 保健",
    "hero.h11": "受过训练的看护员，到府服务，时间由您安排。",
    "hero.p2": "Sutera Care Provider 为巴生谷各地的家庭安排经过审核的看护员——照顾年迈的父母、在家康复的病人，以及任何日常生活需要有人扶持的人。您会有固定的看护员、一份书面护理计划，以及每次探访的记录。",
    "hero.a1": "索取报价",
    "hero.a2": "看看看护员的工作内容",
    "hero.p3": "想应征看护员工作？<a href=\"#careers\">查看看护员职缺&nbsp;→</a>",
    "included.h21": "每项安排都包括",
    "included.p1": "无论您每周只预约三小时，还是需要住家看护，都包含以下六项。",
    "included.li1": "<strong>配对合适的看护员</strong>根据家中使用的语言和所需协助的类型挑选。",
    "included.li2": "<strong>书面护理计划</strong>在第一次探访前与家人商定，不凭假设办事。",
    "included.li3": "<strong>上班及下班打卡</strong>每次探访都有记录，出勤情况不必靠记忆。",
    "included.li4": "<strong>当天的探访记录</strong>简短记下当天的情况，您指定的家人都可以阅读。",
    "included.li5": "<strong>专属协调员</strong>一位熟悉个案、会接听您电话的专人。",
    "included.li6": "<strong>必要时有人替班</strong>若您的看护员身体不适，会由已掌握护理计划的替班看护员接手。",
    "banner.div1": "<strong>我们提供的是看护员——不是护士或医生。</strong>&ensp;我们的团队协助日常起居、个人卫生、行动和陪伴。医疗治疗仍由您自己的医生或持有执照的居家护理（护士）服务提供者负责。",
    "services.h21": "看护员的工作内容",
    "services.p1": "护理可按小时、按天或以住家方式安排。以下每项工作都会在看护员开始服务前写进护理计划，因此大家都清楚服务范围。",
    "services.h31": "日常个人护理",
    "services.p2": "让人在家中保持舒适、清洁和安全的日常协助。",
    "services.li1": "洗澡、淋浴及抹身",
    "services.li2": "更换成人纸尿裤和护垫",
    "services.li3": "穿衣、仪容整理及口腔护理",
    "services.li4": "如厕及失禁护理",
    "services.li5": "协助翻身及预防褥疮",
    "services.li6": "协助进食及准备餐点",
    "services.h32": "行动协助与陪伴",
    "services.p3": "帮助对方保持活动、头脑清醒，并与日常生活保持联系。",
    "services.li7": "转移位置、步行及轮椅协助",
    "services.li8": "陪同前往医院及诊所复诊",
    "services.li9": "温和的医嘱运动及伸展",
    "services.li10": "定时提醒服药",
    "services.li11": "聊天、读书报及陪伴",
    "services.li12": "与照顾对象相关的简单家务",
    "services.h33": "按摩与保健",
    "services.p4": "可单次预约，或加入现有的护理时间表。",
    "services.li13": "放松及舒缓按摩",
    "services.li14": "产后按摩及坐月子协助",
    "services.li15": "为卧床者进行的温和四肢按摩",
    "services.li16": "协助伸展，舒缓僵硬",
    "services.li17": "热敷及舒缓护理",
    "services.li18": "单次探访或每周固定时段",
    "services.p5": "需要的服务不在列表中？请写在报价表格里——如果超出看护员可做的范围，我们会坦白告诉您，并介绍您合适的服务提供者。",
    "conditions.h21": "常见病况的护理",
    "conditions.p1": "大多数家庭来找我们，是因为确诊某种疾病、跌倒或刚出院。我们的看护员不治疗病况——那是医生的工作——但他们了解带着这些病况的日常生活是什么样子，护理计划也会围绕它来制定。",
    "conditions.h31": "失智症与记忆衰退",
    "conditions.p2": "熟悉的面孔和稳定的作息。温和地提醒用餐、梳洗和穿衣，留意防止走失，并耐心回应重复的问题。",
    "conditions.h32": "中风后",
    "conditions.p3": "安全地转移位置，协助单侧无力的情况，陪同完成治疗师安排的居家运动，并陪同前往复健预约。",
    "conditions.h33": "出院回家",
    "conditions.p4": "手术或住院后的头几个星期：协助起身走动、个人护理、餐食，并有人在情况不对劲时及时察觉。",
    "conditions.h34": "帕金森症与年老体弱",
    "conditions.p5": "协助步行和保持平衡，预防在家中跌倒，并不慌不忙地协助进食、穿衣和洗澡。",
    "conditions.h35": "卧床护理",
    "conditions.p6": "定时翻身、床上抹身、更换纸尿裤、检查受压部位的皮肤，以及舒缓的四肢按摩。",
    "conditions.h36": "舒缓护理与临终关怀",
    "conditions.p7": "配合舒缓治疗团队，给予舒适、尊严和陪伴，也让需要休息的家人喘一口气。",
    "how.h21": "服务流程",
    "how.p1": "从第一次联络到第一次探访，通常需要三至五个工作天——紧急情况可更快安排。",
    "how.p2": "家庭们告诉我们，最重要的是居家评估。我们会见照顾对象、了解家中环境，并与您一起制定护理计划，而不是交给您一套标准配套。",
    "how.h31": "告诉我们您的情况",
    "how.p3": "通过简短的电话或报价表格告诉我们：谁需要照顾、需要哪些协助，以及什么时候需要。",
    "how.h32": "居家评估",
    "how.p4": "我们上门拜访，会见照顾对象和家人，一起制定护理计划——工作内容、时数和家中规矩。",
    "how.h33": "配对看护员",
    "how.p5": "我们根据语言、经验和时间推荐合适的看护员。您可在确认前先与对方见面。",
    "how.h34": "开始护理",
    "how.p6": "按商定的时间表开始探访。您可以看到打卡记录和探访记录，协调员也随时可以联络。",
    "why.h21": "家庭为何选择我们",
    "why.div1": "<strong>每位看护员在安排前都经过审核。</strong> 核实身份和工作资格、查询推荐人，并存档警方背景调查记录。",
    "why.div2": "<strong>固定的看护员，而不是轮流来的陌生人。</strong> 对失智症和临终护理来说，连续性最为重要。您会有一位指定看护员和一位指定后备看护员。",
    "why.div3": "<strong>与您商定的书面护理计划。</strong> 服务范围内的每项工作都列明。不凭假设，任何更改都须双方同意，而不是默默增加。",
    "why.div4": "<strong>发生了什么，您都看得到。</strong> 打卡时间和探访记录都会存档，家人当天就能阅读。",
    "why.div5": "<strong>说家里习惯的语言。</strong> 我们的团队会说马来语、英语、华语、粤语和淡米尔语——我们会按照照顾对象最习惯的语言来配对。",
    "why.div6": "<strong>清楚说明我们不做什么。</strong> 我们不打针、不处理伤口敷料、不管理静脉输液管，也不更换导尿管。把这点说清楚，其他承诺才值得信赖。",
    "team.h21": "认识我们的看护员",
    "team.p1": "家人都想知道来到家门口的是谁。以下每位看护员都经过面对面面试，并通过推荐人查询和警方背景调查——在确认护理安排前，您也会先见到您的看护员。",
    "team.p2": "乐龄护理",
    "team.h31": "个人简介即将推出",
    "team.p3": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "team.p4": "住家看护",
    "team.h32": "个人简介即将推出",
    "team.p5": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "team.p6": "失智症护理",
    "team.h33": "个人简介即将推出",
    "team.p7": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "team.p8": "出院回家",
    "team.h34": "个人简介即将推出",
    "team.p9": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "team.p10": "按摩与保健",
    "team.h35": "个人简介即将推出",
    "team.p11": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "team.p12": "护理协调员",
    "team.h36": "个人简介即将推出",
    "team.p13": "我们正在陆续加入团队成员的介绍。欢迎向您的协调员询问您所在地区可安排的看护员。",
    "quote.h21": "索取报价",
    "quote.p1": "<strong>谢谢您。</strong>我们已收到您的询问，将在一个工作天内回复。",
    "quote.p2": "安排护理的人往往不是接受护理的人，所以我们两者的资料都需要。填写此表格不代表您须作出任何承诺。您的报价会根据您的情况逐项列明——时数、协助程度和地点——事后不会另加收费。",
    "quote.h31": "护理询问",
    "quote.p3": "我们会在一个工作天内回复。",
    "quote.legend1": "您的资料",
    "quote.label1": "您的姓名 *",
    "quote.label2": "电话 / WhatsApp *",
    "quote.label3": "电邮 *",
    "quote.label4": "您与需要护理者的关系",
    "quote.option1": "— 请选择 —",
    "quote.option2": "儿子或女儿",
    "quote.option3": "配偶或伴侣",
    "quote.option4": "其他家人",
    "quote.option5": "朋友或邻居",
    "quote.option6": "我本人需要护理",
    "quote.option7": "医院或诊所转介",
    "quote.legend2": "需要护理者",
    "quote.label5": "对方姓名 *",
    "quote.label6": "年龄",
    "quote.label7": "地区 / 市镇 *",
    "quote.label8": "行动能力",
    "quote.option8": "— 请选择 —",
    "quote.option9": "可自行步行",
    "quote.option10": "需用拐杖或助行架",
    "quote.option11": "使用轮椅",
    "quote.option12": "大部分时间卧床",
    "quote.label9": "需要哪些协助？",
    "quote.label10": "您预计的日子和时间",
    "quote.button1": "提交询问",
    "quote.p4": "这些资料只用于准备您的报价和安排评估。我们不会出售或分享您的资料。请参阅我们的<a href=\"privacy.html\">隐私声明</a>。",
    "quote.h32": "接下来会怎样",
    "quote.li1": "<strong>一个工作天内</strong>协调员会致电或通过 WhatsApp 联络您，了解情况。",
    "quote.li2": "<strong>居家评估</strong>我们上门拜访，会见照顾对象，并与您一起制定护理计划。",
    "quote.li3": "<strong>逐项列明的报价</strong>时数、协助程度及任何附加费，逐行列出。",
    "quote.li4": "<strong>与看护员见面</strong>在确认任何安排前，您可先与推荐的看护员见面。",
    "quote.p5": "想直接聊一聊？<br><a href=\"#contact\">致电或 WhatsApp 护理热线&nbsp;→</a>",
    "careers.p1": "招聘",
    "careers.h21": "配合您生活的看护员工作",
    "careers.p2": "我们正在巴生谷各地招聘看护员——全职、兼职、住家及周末皆可。最重要的是合适的性格：耐心、可靠，并尊重您所照顾的人。其余的，我们会协助您。",
    "careers.p3": "<strong>谢谢您的应征。</strong>我们会在一周内联络入选的应征者。",
    "careers.h31": "看护员为何选择与我们合作",
    "careers.h41": "自选工作方式",
    "careers.p4": "全职、兼职、住家或只做周末。告诉我们您的时间，我们会按此安排工作。",
    "careers.h42": "在家附近工作",
    "careers.p5": "工作安排会配合您表示可以工作的地区，以及您会说的语言。",
    "careers.h43": "培训与晋升",
    "careers.p6": "在第一份工作安排前，您会接受我们护理标准的入职培训，日后也有机会逐步接手失智症和住家护理等专门个案。",
    "careers.h44": "一个应用程序搞定",
    "careers.p7": "通过 Sutera 看护员应用程序查看您的时间表和护理计划、上下班打卡，以及填写探访记录。",
    "careers.h45": "协调员做您的后盾",
    "careers.p8": "一位熟悉您个案的专属协调员，会协助处理与家庭之间的问题，并在您身体不适时安排替班。",
    "careers.h46": "清楚的工作范围",
    "careers.p9": "我们绝不会要求您做护士的工作。每项工作都列在护理计划里，并在您开始前已与家人商定。",
    "careers.h32": "您的工作内容",
    "careers.li1": "洗澡、淋浴、床上抹身及更换纸尿裤",
    "careers.li2": "穿衣、仪容整理及口腔护理",
    "careers.li3": "安全转移位置、步行及轮椅协助",
    "careers.li4": "准备餐点及协助进食",
    "careers.li5": "定时提醒服药",
    "careers.li6": "陪同前往医院及诊所复诊",
    "careers.li7": "温和运动、聊天及陪伴",
    "careers.li8": "与照顾对象相关的简单家务",
    "careers.h33": "我们在寻找这样的您",
    "careers.li9": "有耐心、善良、可靠——这是最重要的",
    "careers.li10": "可在马来西亚合法工作",
    "careers.li11": "能够胜任个人护理工作",
    "careers.li12": "至少会说马来语、英语、华语、粤语或淡米尔语其中一种",
    "careers.li13": "有经验或看护证书更佳，但并非必要",
    "careers.li14": "愿意接受推荐人查询和警方背景调查",
    "careers.h34": "招聘流程",
    "careers.h35": "应征",
    "careers.p10": "填写以下表格，大约只需三分钟。",
    "careers.h36": "面试",
    "careers.p11": "入选的应征者会在一周内获得联络，并进行面对面面试。",
    "careers.h37": "背景调查与入职培训",
    "careers.p12": "我们会查询推荐人、完成警方背景调查，并向您介绍我们的护理标准和应用程序。",
    "careers.h38": "第一份工作安排",
    "careers.p13": "我们根据地区、语言和时间为您配对家庭，并在护理开始前为双方介绍。",
    "careers.h39": "应征加入我们",
    "careers.p14": "现有职位：乐龄护理、居家护理及住家看护员、按摩与保健理疗师，以及护理协调员。",
    "careers.legend1": "应征者资料",
    "careers.label1": "全名 *",
    "careers.label2": "电话 / WhatsApp *",
    "careers.label3": "电邮",
    "careers.label4": "可工作的地区 *",
    "careers.legend2": "职位与经验",
    "careers.label5": "应征职位 *",
    "careers.option1": "— 请选择 —",
    "careers.option2": "看护员——乐龄护理",
    "careers.option3": "看护员——居家护理",
    "careers.option4": "住家看护员",
    "careers.option5": "按摩与保健理疗师",
    "careers.option6": "护理协调员（办公室）",
    "careers.option7": "其他",
    "careers.label6": "经验年数",
    "careers.label7": "可工作时间",
    "careers.option8": "— 请选择 —",
    "careers.option9": "全职",
    "careers.option10": "兼职",
    "careers.option11": "住家",
    "careers.option12": "只限周末",
    "careers.label8": "会说的语言",
    "careers.label9": "培训或证书",
    "careers.button1": "提交应征",
    "careers.p15": "入选的应征者将进行面对面面试。工作安排须通过推荐人查询和警方背景调查。",
    "careers.h310": "应征者常问的问题",
    "careers.summary1": "我没有看护经验，还可以应征吗？",
    "careers.p16": "可以。请告诉我们任何相关经历——照顾过家人也算。新看护员会先从较简单的个案开始。",
    "careers.summary2": "我可以选择工作时间吗？",
    "careers.p17": "可以。告诉我们您想做全职、兼职、住家还是周末，我们会按此安排工作。",
    "careers.summary3": "工作地点在哪里？",
    "careers.p18": "在巴生谷各地的客户家中。我们会按照您表示可以工作的地区来配对，让交通保持合理。",
    "careers.summary4": "我如何得知自己的时间表？",
    "careers.p19": "通过 Sutera 看护员应用程序：您的班次、每位客户的护理计划、上下班打卡，以及探访记录。",
    "careers.summary5": "你们聘请护士吗？",
    "careers.p20": "不聘请护士从事护理（医疗）工作。我们安排的是协助日常起居的看护员，而不是医疗护理。如果护士想做看护工作，欢迎以看护员身份应征。",
    "guides.h21": "给家人的指南",
    "guides.p1": "当家中有人开始需要协助时，家人要面对各种决定——这里提供浅白实用的建议。免费阅读，无需注册。",
    "guides.span1": "选择护理方式",
    "guides.h31": "看护员、女佣还是疗养院？",
    "guides.p2": "每种选择实际涉及什么、适合哪些情况，以及做决定前应该问的问题。",
    "guides.span2": "阅读指南&nbsp;→",
    "guides.span3": "出院",
    "guides.h32": "出院回家：检查清单",
    "guides.p3": "出院当天之前要准备好什么，以及如何度过回家后的头两个星期。",
    "guides.span4": "阅读指南&nbsp;→",
    "guides.span5": "居家安全",
    "guides.h33": "预防在家中跌倒",
    "guides.p4": "逐个房间检查最重要的改动，大多数花费不多甚至免费。",
    "guides.span6": "阅读指南&nbsp;→",
    "faq.h21": "家人常问的问题",
    "faq.summary1": "你们的看护员是护士吗？",
    "faq.p1": "不是，我们对此非常谨慎。我们的看护员协助日常起居——洗澡、更换纸尿裤、喂食、行动、陪伴及陪同复诊。他们不打针、不处理伤口敷料、不管理静脉输液管、不插入或更换导尿管，也不作任何医疗决定。如果您的情况需要这些服务，您需要的是持有执照的居家护理（护士）服务提供者，我们会如实告诉您，而不是接下这项预约。",
    "faq.summary2": "看护员可以给药吗？",
    "faq.p2": "他们可以定时提醒服药、递上已分好的剂量，并记录已经服用。他们不会调整剂量，也不会以注射方式给予任何药物。药物应由家人或药剂师预先分装到每周药盒中。",
    "faq.summary3": "如果我们与看护员合不来怎么办？",
    "faq.p3": "请告诉您的协调员，我们会更换安排。在居家护理中，配对不合适是很正常的事，与其忍耐，不如在第一周就提出来。",
    "faq.summary4": "如果我们的看护员生病了怎么办？",
    "faq.p4": "您的协调员会安排一位已掌握护理计划的替班看护员。住家看护安排则会在护理开始前，先商定一位指定的替班看护员。",
    "faq.summary5": "你们在巴生谷以外的地区提供护理吗？",
    "faq.p5": "我们先从巴生谷开始。其他地区的询问我们也会受理，并如实告诉您我们能否派人，而不是先答应却做不到。",
    "faq.summary6": "家人如何掌握最新情况？",
    "faq.p6": "看护员每次探访都会上下班打卡，并写下简短的探访记录。获授权的家人当天就能阅读，也能看到下一周的时间表。",
    "faq.summary7": "谁可以查看我家人的资料？",
    "faq.p7": "只有负责的看护员、您的协调员，以及您指定的家人。每次查阅都有记录。根据马来西亚《2010年个人资料保护法令》，我们将健康资料视为敏感个人资料处理。",
    "faq.summary8": "Sutera Care Provider 和 Sutera Cares 是同一个机构吗？",
    "faq.p8": "两者有关联，但各自独立。Sutera Cares 是为马来西亚难民而设的非营利医疗基金。Sutera Care Provider 则是一家商业居家护理公司。这里的付费客户不会自动资助该慈善机构，慈善捐款也绝不会用来补贴这项业务——两者的账目完全分开。",
    "contact.h21": "联络我们",
    "contact.h31": "护理热线",
    "contact.p1": "每日早上8时至晚上10时 · 马来语、英语、华语、淡米尔语",
    "contact.h32": "电邮",
    "contact.p2": "询问、报价及应征。",
    "contact.h33": "服务地区",
    "contact.p3": "其他州属可另行安排。",
    "sister.p1": "<strong>我们的非营利姊妹机构。</strong> Sutera Cares 是一个由社区主导、服务马来西亚难民的医疗基金——安全分娩、新生儿护理与紧急医疗。独立机构，账目分开。<a href=\"https://www.suteracares.org/\">前往 Sutera Cares&nbsp;→</a>",
    "footer.p1": "Sutera Care Provider——马来西亚居家及乐龄护理的专业看护员。",
    "footer.p2": "Sutera Care Provider 提供非医疗看护员。我们不提供医生、护士或任何临床或医疗治疗，本网站的内容也不构成医疗建议。个人及健康资料均根据《2010年个人资料保护法令》作为敏感个人资料处理。",
    "footer.p3": "<a href=\"/portal/\">员工、看护员及家属入口</a><a href=\"#careers\">招聘</a><a href=\"#guides\">指南</a><a href=\"privacy.html\">隐私声明</a>",
    "wa.span1": "WhatsApp 我们",
    "header.label1": "Sutera Care Provider——首页",
    "header.label2": "选择语言",
    "header.label3": "主选单",
    "quote.ph1": "例如：每天早上洗澡及更换纸尿裤、每两周陪同到医院复诊、下午陪伴。",
    "quote.ph2": "例如：星期一至五，早上9时至下午5时",
    "careers.ph1": "例如：马来语、英语、华语",
    "careers.ph2": "看护课程、急救证书、以往雇主，或任何我们应该知道的事。",
    "wa.label1": "通过 WhatsApp 与我们聊天"
  };

  /* ---------------------------------------------------------
     Engine
     --------------------------------------------------------- */
  var en = {};
  var DICTS = { en: en, ms: ms, zh: zh };
  var textNodes = [];
  var attrNodes = [];
  var canonicalBase = '';

  function meta(attr, value) {
    return document.head.querySelector('meta[' + attr + '="' + value + '"]');
  }

  function canonicalLink() {
    return document.head.querySelector('link[rel="canonical"]');
  }

  function each(sel, fn) {
    Array.prototype.forEach.call(document.querySelectorAll(sel), fn);
  }

  function capture() {
    each('[data-i18n]', function (el) {
      var key = el.getAttribute('data-i18n');
      textNodes.push({ el: el, key: key });
      en[key] = el.innerHTML;
    });
    [['data-i18n-ph', 'placeholder'], ['data-i18n-label', 'aria-label']].forEach(function (pair) {
      each('[' + pair[0] + ']', function (el) {
        var key = el.getAttribute(pair[0]);
        attrNodes.push({ el: el, key: key, attr: pair[1] });
        en[key] = el.getAttribute(pair[1]);
      });
    });
    en['meta.title'] = document.title;
    en['meta.desc'] = meta('name', 'description').content;
    en['meta.ogTitle'] = meta('property', 'og:title').content;
    en['meta.ogDesc'] = meta('property', 'og:description').content;

    var canon = canonicalLink();
    canonicalBase = canon ? canon.href : window.location.origin + window.location.pathname;
  }

  function stringFor(dict, key) {
    return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : en[key];
  }

  function apply(lang) {
    var dict = DICTS[lang] || en;

    textNodes.forEach(function (n) { n.el.innerHTML = stringFor(dict, n.key); });
    attrNodes.forEach(function (n) { n.el.setAttribute(n.attr, stringFor(dict, n.key)); });

    document.title = stringFor(dict, 'meta.title');
    meta('name', 'description').content = stringFor(dict, 'meta.desc');
    meta('property', 'og:title').content = stringFor(dict, 'meta.ogTitle');
    meta('property', 'og:description').content = stringFor(dict, 'meta.ogDesc');

    document.documentElement.setAttribute('lang', HTML_LANG[lang] || lang);

    var url = canonicalBase + (lang === DEFAULT_LANG ? '' : '?lang=' + lang);
    var canon = canonicalLink();
    if (canon) canon.href = url;
    var ogUrl = meta('property', 'og:url');
    if (ogUrl) ogUrl.content = url;

    each('.lang-switch button', function (btn) {
      btn.setAttribute('aria-pressed', String(btn.getAttribute('data-lang') === lang));
    });
  }

  function remember(lang) {
    try { localStorage.setItem(STORAGE_KEY, lang); } catch (e) { /* private mode */ }
  }

  function stored() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function fromQuery() {
    var m = /[?&]lang=([a-zA-Z-]+)/.exec(window.location.search);
    return m ? m[1].toLowerCase().slice(0, 2) : null;
  }

  /* First visit only: follow the browser's language if we speak it. */
  function fromBrowser() {
    var tags = navigator.languages || [navigator.language || ''];
    for (var i = 0; i < tags.length; i++) {
      var tag = String(tags[i]).toLowerCase();
      if (tag.indexOf('zh') === 0) return 'zh';
      if (tag.indexOf('ms') === 0 || tag.indexOf('id') === 0) return 'ms';
      if (tag.indexOf('en') === 0) return 'en';
    }
    return null;
  }

  function pick() {
    var q = fromQuery();
    if (q && DICTS.hasOwnProperty(q)) return q;
    var s = stored();
    if (s && DICTS.hasOwnProperty(s)) return s;
    return fromBrowser() || DEFAULT_LANG;
  }

  /* Keeps the address bar shareable: ?lang=ms opens the Malay page. */
  function syncUrl(lang) {
    if (!window.history || !window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (lang === DEFAULT_LANG) {
      url.searchParams.delete('lang');
    } else {
      url.searchParams.set('lang', lang);
    }
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
  }

  function setLanguage(lang, options) {
    apply(lang);
    remember(lang);
    syncUrl(lang);
    if (options && options.focusSwitcher) {
      var active = document.querySelector('.lang-switch button[data-lang="' + lang + '"]');
      if (active) active.focus();
    }
  }

  function init() {
    capture();
    var lang = pick();
    if (lang !== DEFAULT_LANG) setLanguage(lang); else apply(lang);

    document.addEventListener('click', function (event) {
      var btn = event.target.closest ? event.target.closest('.lang-switch button') : null;
      if (!btn) return;
      setLanguage(btn.getAttribute('data-lang'), { focusSwitcher: true });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
