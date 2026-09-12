/* ============================================================
   Sutera Cares — language switcher (EN / BM / 中文)

   English lives in index.html and is snapshotted on load, so it
   is never duplicated here. Only the translations live below.

   To add a string: give the element a data-i18n="some.key"
   attribute in index.html, then add "some.key" to ms and zh.
   Values are HTML — inline <strong>/<em>/<a>/<br> are fine.
   ============================================================ */

(function () {
  'use strict';

  var STORAGE_KEY = 'sutera-lang';
  var DEFAULT_LANG = 'en';

  /* The html lang attribute written for each language. */
  var HTML_LANG = { en: 'en', ms: 'ms', zh: 'zh-Hans' };

  /* ---------------------------------------------------------
     Bahasa Malaysia
     --------------------------------------------------------- */
  var ms = {
    'meta.title': 'Sutera Cares — Bantuan perubatan untuk pelarian di Malaysia',
    'meta.desc': 'Sutera Cares ialah dana perubatan pimpinan komuniti untuk pelarian di Malaysia. Kami membayar hospital secara terus untuk kelahiran selamat dan kecemasan perubatan, menemani keluarga sepanjang prosesnya, dan menerbitkan setiap ringgit.',
    'meta.ogTitle': 'Sutera Cares — Setiap ibu selamat bersalin. Setiap nyawa berharga.',
    'meta.ogDesc': 'Bantuan perubatan pimpinan komuniti untuk pelarian di Malaysia: kesihatan ibu, penjagaan bayi baru lahir dan kecemasan. Bayaran terus kepada hospital, telus sepenuhnya.',

    'brand.aria': 'Sutera Cares — laman utama',
    'brand.tag': 'Bantuan perubatan untuk pelarian di Malaysia',
    'header.hotlineLabel': 'Talian WhatsApp · 8 pagi–10 malam setiap hari',
    'header.cta': 'Bayar Bil Saya',

    'nav.aria': 'Utama',
    'nav.programmes': 'Program',
    'nav.how': 'Cara kami bekerja',
    'nav.promises': 'Janji kami',
    'nav.about': 'Tentang kami',
    'nav.faq': 'Soal Jawab',
    'nav.contact': 'Hubungi',
    'lang.aria': 'Pilih bahasa',

    'hero.eyebrow': 'Pimpinan komuniti · Bayaran terus kepada hospital · Telus sepenuhnya',
    'hero.h1': 'Setiap ibu selamat bersalin.<br>Setiap nyawa berharga.',
    'hero.sub': 'Every mother a safe birth. Every life a chance.',
    'hero.lead': 'Sutera Cares ialah dana perubatan pimpinan komuniti untuk pelarian di Malaysia. Apabila seorang ibu pelarian — atau sebuah keluarga yang berdepan kecemasan perubatan — memerlukan wang dalam beberapa jam, kami membayar hospital secara terus, menemani keluarga itu melalui sistemnya, dan menerbitkan setiap ringgit.',
    'hero.cta1': 'Derma sekarang',
    'hero.cta2': 'Tajai Kelahiran Selamat — RM 2,000',
    'hero.help': 'Perlukan bantuan perubatan untuk keluarga anda? <a href="#contact">Hubungi talian kami&nbsp;→</a>',

    'banner': '<strong>Terus kepada hospital. Tidak pernah tunai.</strong>&ensp;Tiga kemas kini bagi setiap kes — <em>Dibiayai → Dirawat → Pulih</em> — dan setiap ringgit dalam lejar awam.',

    'need.h2': 'Mengapa Sutera Cares wujud',
    'need.stat1': 'pelarian dan pencari suaka berdaftar dengan UNHCR di Malaysia — tanpa status sah, tanpa hak untuk bekerja, dan tanpa perlindungan kesihatan awam.',
    'need.stat2': 'ialah deposit yang boleh diminta hospital sebelum seseorang bersalin — selalunya menyamai pendapatan tiga bulan sebuah keluarga pelarian.',
    'need.stat3num': '24–48 jam',
    'need.stat3': 'ialah kepantasan Sutera Cares bergerak dari rujukan kecemasan kepada keputusan pembiayaan — kerana kecemasan memerlukan wang dalam beberapa jam, bukan beberapa minggu.',
    'need.note': 'Di hospital kerajaan, pelarian membayar kadar “warga asing”. Kad UNHCR mengurangkan bil sebanyak separuh, tetapi satu kelahiran masih boleh mencecah ribuan ringgit — dan bil yang tidak dijelaskan membawa hutang, sekatan daripada rawatan akan datang, serta ketakutan. Kehamilan ialah sebab paling lazim sebuah keluarga pelarian berdepan bil perubatan yang besar. Kecemasan pula yang paling memusnahkan. Klinik untuk pelarian memang ada; apa yang tiada ialah cara yang pantas dan dipimpin komuniti untuk <em>membayar bil itu dan menemani keluarga melaluinya</em>. Itulah jurang yang kami isi.',

    'prog.h2': 'Tiga program, satu janji',
    'prog.lead': 'Kami tidak mengendalikan klinik dan kami tidak pernah memberikan wang tunai. Kami membiayai rawatan, memandu keluarga melalui sistem, dan merujuk selebihnya kepada rakan kongsi yang dipercayai.',
    'prog.c1h': 'Dana Kecemasan Sutera',
    'prog.c1p': 'Deposit dan bayaran bil pada hari yang sama untuk kes yang mengancam nyawa — serta permohonan pengurangan yuran dan rundingan ansuran dengan jabatan kerja sosial hospital.',
    'prog.c1amt': '<strong>RM 5,000</strong> membiayai satu Talian Hayat Kecemasan',
    'prog.c2h': 'Kelahiran Selamat Sutera',
    'prog.c2p': 'Penjagaan antenatal, kos bersalin di klinik rakan kongsi dan hospital kerajaan, susulan selepas bersalin dan untuk bayi baru lahir, kit ibu dan bayi, serta bantuan mendaftarkan kelahiran.',
    'prog.c2amt': '<strong>RM 2,000</strong> menaja satu kelahiran selamat',
    'prog.c3h': 'Pendampingan Rawatan',
    'prog.c3p': 'Talian bantuan dan pendamping yang bertutur dalam bahasa keluarga itu — jurubahasa, iringan ke hospital, urusan dokumen, dan rujukan. Orang yang melangkah masuk bersamanya.',
    'prog.c3amt': '<strong>Dari RM 20/bulan</strong> — Sutera Circle membiayai para pendamping',

    'how.h2': 'Bagaimana sesuatu kes berjalan',
    'how.s1h': 'Hubungi',
    'how.s1p': 'Sebuah keluarga, pemimpin komuniti, klinik atau pekerja sosial hospital menghubungi talian WhatsApp kami — dalam Bahasa Malaysia, Inggeris, Rohingya, Burma atau Arab.',
    'how.s2h': 'Sahkan',
    'how.s2p': 'Kami menyemak dokumen UNHCR, kertas perubatan dan sebut harga daripada hospital sendiri, serta mengesahkannya dengan pemimpin komuniti — dalam masa 24 jam bagi kes kecemasan.',
    'how.s3h': 'Biayai',
    'how.s3p': 'Dana Kecemasan atau kempen khusus menanggung bil itu. Bayaran dibuat terus kepada hospital atau klinik — tidak pernah tunai, dan setiap resit diterbitkan.',
    'how.s4h': 'Temani',
    'how.s4p': 'Seorang pendamping duduk bersama keluarga sepanjang kemasukan, rawatan dan urusan dokumen — termasuk sijil kelahiran bayi dan pendaftaran UNHCR.',
    'how.s5h': 'Susulan',
    'how.s5p': 'Pemeriksaan selepas bersalin dan pemulihan pada hari ke-7 dan minggu ke-6. Kes ditutup bersama hasilnya, dan setiap penderma menerima kemas kini ketiga: <em>Pulih</em>.',

    'promises.h2': 'Janji kami — peraturan yang kami terbitkan',
    'promises.p1': '<strong>Terus kepada hospital sahaja.</strong> Kami membayar klinik dan hospital. Kami tidak pernah menyerahkan wang tunai kepada sesiapa.',
    'promises.p2': '<strong>100% sumbangan kempen pergi kepada pesakit.</strong> Kos operasi dibiayai secara berasingan, oleh penderma bulanan Sutera Circle.',
    'promises.p3': '<strong>Setiap kes ada dalam lejar awam.</strong> Jumlah yang dikumpul, jumlah yang dibayar, resitnya — dengan privasi pesakit dilindungi.',
    'promises.p4': '<strong>Tiga kemas kini bagi setiap kes.</strong> Dibiayai → Dirawat → Pulih, kepada setiap penderma yang memberi.',
    'promises.p5': '<strong>Lebihan tidak pernah disimpan diam-diam.</strong> Jika sesebuah kempen mengumpul lebih daripada nilai bil, lebihan itu masuk ke Dana Kecemasan — dinyatakan pada setiap halaman kempen.',
    'promises.p6': '<strong>Dua orang meluluskan setiap bayaran.</strong> Bendahari bebas, penyesuaian akaun setiap bulan, penyata kewangan awam setiap suku tahun.',
    'promises.ledger': '<strong>Lejar kes secara langsung</strong> — kes pertama kami akan muncul di sini, setiap satu dengan jumlah yang dikumpul, jumlah yang dibayar kepada hospital, dan resitnya.',

    'donate.h2': 'Cara untuk memberi',
    'donate.lead': 'Setiap pilihan sumbangan sepadan dengan kos yang sebenar — itulah yang menjadikannya boleh dipercayai.',
    'donate.d1h': 'Kit Ibu &amp; Bayi',
    'donate.d1p': 'Keperluan bayi baru lahir dan lawatan pertama selepas bersalin.',
    'donate.d2h': 'Pakej Antenatal',
    'donate.d2p': '4–6 lawatan klinik, ujian, imbasan dan suplemen.',
    'donate.d3h': 'Tajai Kelahiran Selamat',
    'donate.d3p': 'Satu kelahiran yang disokong, dari mula hingga akhir.',
    'donate.d4h': 'Talian Hayat Kecemasan',
    'donate.d4p': 'Satu kemasukan kecemasan atau pembedahan caesarean.',
    'donate.d5amt': 'RM 20+ /bln',
    'donate.d5h': 'Sutera Circle',
    'donate.d5p': 'Sumbangan bulanan yang membiayai pendamping, talian bantuan dan pengangkutan.',
    'donate.d6amt': 'Zakat',
    'donate.d6h': 'Kumpulan zakat',
    'donate.d6p': 'Dikhaskan untuk asnaf yang layak. Dana am memberi manfaat kepada semua orang, tanpa mengira agama.',
    'donate.qrh': 'DuitNow QR',
    'donate.qr': 'Kod QR<br>akan menyusul bersama<br>kempen pertama kami',
    'donate.bankh': 'Pindahan bank',
    'donate.bank': '[Nama akaun organisasi rakan kongsi]<br>[Bank · Nombor akaun]<br>Rujukan: <strong>SUTRA</strong>',
    'donate.onlineh': 'Halaman dermaan dalam talian',
    'donate.online': 'Halaman dermaan kad dan FPX — akan menyusul.',
    'donate.note': 'Sumbangan diterima oleh organisasi rakan kongsi kami yang berdaftar di Malaysia dan dibayar terus kepada penyedia rawatan kesihatan. Sumbangan <strong>belum layak untuk pelepasan cukai</strong> — kami menyatakannya dengan jelas, dan kami akan memberitahu anda sebaik sahaja ia berubah.',

    'about.h2': 'Siapa kami',
    'about.p1': 'Perkataan <em>sutera</em> dalam bahasa Melayu berasal daripada <em>sutra</em> dalam bahasa Sanskrit, yang bermaksud “benang”. <strong>Benang yang menahan: selembut sutera, sekuat tali hayat.</strong>',
    'about.p2': 'Sutera Cares diasaskan oleh seorang pemimpin komuniti pelarian yang mempunyai kepercayaan dan jangkauan dalam komuniti pelarian di Malaysia, bersama seorang pengasas bersama warga Malaysia yang dihormati dalam komuniti tempatan, masjid dan institusi — dua bahagian yang diperlukan oleh masalah ini.',
    'about.p3': 'Dana ini ditadbir oleh sebuah jawatankuasa bebas Malaysia yang dianggotai doktor, seorang akauntan dan seorang peguam, manakala sebuah Majlis Penasihat Komuniti yang terdiri daripada pemimpin pelarian memastikan kerja ini kekal dipimpin komuniti. Kami beroperasi di bawah sebuah organisasi rakan kongsi yang berdaftar di Malaysia sementara pendaftaran pertubuhan kami sendiri sedang diuruskan.',
    'about.p4': 'Kami membantu mana-mana pelarian atau pencari suaka — apa jua etnik, apa jua agama — bermula di Lembah Klang.',
    'about.cardh': 'Apa yang sengaja tidak kami lakukan',
    'about.li1': 'Mengendalikan klinik — kami membiayai dan memandu rawatan di klinik yang sedia ada.',
    'about.li2': 'Memberi wang tunai kepada pesakit — bayaran pergi kepada penyedia rawatan.',
    'about.li3': 'Bertindih dengan NGO lain — kami mengisi jurang pembiayaan dan merujuk selebihnya.',
    'about.li4': 'Memberi pinjaman — bantuan ialah pemberian, bukan hutang.',
    'about.li5': 'Menerbitkan wajah, nama atau lokasi sesiapa tanpa kebenaran.',

    'partners.h2': 'Bekerjasama dengan klinik, hospital dan NGO',
    'partners.note': 'Kami sedang memuktamadkan terma dengan klinik dan hospital rakan kongsi. Nama mereka akan muncul di sini apabila perjanjian ditandatangani — kami hanya menunjukkan apa yang benar.<br><a href="#contact">Mengendalikan klinik atau NGO? Berbincanglah dengan kami tentang kerjasama&nbsp;→</a>',

    'vol.h2': 'Sutera digerakkan oleh sukarelawan',
    'vol.p': 'Pendamping, jurubahasa, petugas perubatan, pencerita, pengumpul dana — dua jam seminggu mampu mengubah hari terburuk seseorang.',
    'vol.btn': 'Jadi sukarelawan',

    'faq.h2': 'Soalan yang ditanya penderma',
    'faq.q1': 'Mengapa pelarian, sedangkan rakyat Malaysia juga memerlukan bantuan?',
    'faq.a1': 'Rakyat Malaysia yang susah mempunyai skim kerajaan dan rawatan bersubsidi; pelarian tidak mempunyai apa-apa pun. Kasih bukan sesuatu yang terhad — ramai penderma kami turut menyumbang kepada badan amal Malaysia, dan kami menyokong mereka.',
    'faq.q2': 'Adakah ini sah di sisi undang-undang?',
    'faq.a2': 'Ya. Sumbangan diterima oleh sebuah organisasi berdaftar — rakan kongsi Malaysia kami buat masa ini, dan pertubuhan kami sendiri setelah diluluskan — dan dibayar terus kepada hospital dan klinik.',
    'faq.q3': 'Adakah sumbangan saya layak untuk pelepasan cukai?',
    'faq.a3': 'Belum lagi. Status pengecualian cukai di Malaysia mengambil masa kira-kira dua tahun akaun yang diaudit. Jika sesuatu sumbangan boleh disalurkan melalui rakan kongsi yang kelulusannya merangkumi sumbangan itu, kami akan menyatakannya dengan jelas.',
    'faq.q4': 'Ke manakah perginya wang saya?',
    'faq.a4': '100% sumbangan kempen pergi kepada pesakit; kos operasi dibiayai oleh penderma bulanan Sutera Circle. Setiap kes muncul dalam lejar awam bersama resitnya.',
    'faq.q5': 'Bagaimana anda tahu sesuatu kes itu benar?',
    'faq.a5': 'Dokumen UNHCR, kertas perubatan, sebut harga daripada hospital sendiri, pengesahan oleh pemimpin komuniti — dan kami mengesahkannya dengan hospital sebelum sebarang bayaran dibuat.',
    'faq.q6': 'Bagaimana jika anda mengumpul lebih daripada keperluan sesuatu kes?',
    'faq.a6': 'Lebihan itu masuk ke Dana Kecemasan untuk keluarga yang seterusnya. Ayat ini muncul pada setiap halaman kempen.',
    'faq.q7': 'Bolehkah saya menyalurkan zakat?',
    'faq.a7': 'Boleh — kepada kumpulan zakat yang dikhaskan untuk asnaf yang layak. Dana am pula memberi manfaat kepada setiap pelarian, tanpa mengira agama.',
    'faq.q8': 'Bolehkah syarikat saya terlibat?',
    'faq.a8': 'Boleh: pakej Penaja Kelahiran Selamat (RM 10,000 menaja lima kelahiran, dengan laporan hasil atas nama syarikat), hari kit ibu dan bayi, serta pendamping sukarelawan daripada pasukan anda.',

    'contact.h2': 'Hubungi kami',
    'contact.c1h': 'Talian WhatsApp',
    'contact.c1p': '8 pagi–10 malam setiap hari · BM, Inggeris, Rohingya, Burma, Arab',
    'contact.c2h': 'E-mel',
    'contact.c2p': 'Penderma, rakan kongsi, sukarelawan dan media.',
    'contact.c3h': 'Kawasan kami',
    'contact.c3big': 'Lembah Klang, Malaysia',
    'contact.c3p': 'Pulau Pinang dan Johor melalui rujukan.',
    'contact.emergency': '<strong>Dalam kecemasan yang mengancam nyawa, pergi ke jabatan kecemasan hospital yang terdekat dahulu — rawatan didahulukan sebelum bil.</strong> Kemudian hubungi kami, dan kami akan uruskan bil itu.',

    'footer.mission': 'Sutera Cares — benang yang menahan. Bantuan perubatan yang pantas, penuh belas kasihan dan bertanggungjawab untuk ibu pelarian dan kes kecemasan di Malaysia.',
    'footer.legal': 'Sumbangan diterima oleh organisasi rakan kongsi kami yang berdaftar di Malaysia dan dibayar terus kepada penyedia rawatan kesihatan; pendaftaran pertubuhan sedang diuruskan. Sumbangan belum layak untuk pelepasan cukai. Kami tidak pernah menerbitkan nama, wajah atau lokasi pesakit tanpa kebenaran.',
    'footer.copy': '© 2026 Sutera Cares · Kuala Lumpur, Malaysia'
  };

  /* ---------------------------------------------------------
     中文（简体）
     --------------------------------------------------------- */
  var zh = {
    'meta.title': 'Sutera Cares — 为马来西亚难民提供医疗援助',
    'meta.desc': 'Sutera Cares 是一个由社区主导、服务马来西亚难民的医疗基金。我们直接向医院支付安全分娩与紧急医疗的费用，全程陪伴家庭，并公开每一分令吉。',
    'meta.ogTitle': 'Sutera Cares — 每位母亲平安生产，每条生命都有机会。',
    'meta.ogDesc': '由社区主导，为马来西亚难民提供医疗援助：孕产健康、新生儿护理与紧急医疗。直接付予医院，账目完全公开。',

    'brand.aria': 'Sutera Cares — 首页',
    'brand.tag': '为马来西亚难民提供医疗援助',
    'header.hotlineLabel': 'WhatsApp 热线 · 每日早上8点至晚上10点',
    'header.cta': '支付医药费',

    'nav.aria': '主要导航',
    'nav.programmes': '援助计划',
    'nav.how': '运作方式',
    'nav.promises': '我们的承诺',
    'nav.about': '关于我们',
    'nav.faq': '常见问题',
    'nav.contact': '联络我们',
    'lang.aria': '选择语言',

    'hero.eyebrow': '社区主导 · 直付医院 · 账目全公开',
    'hero.h1': '每位母亲平安生产。<br>每条生命都有机会。',
    'hero.sub': 'Every mother a safe birth. Every life a chance.',
    'hero.lead': 'Sutera Cares 是一个由社区主导、服务马来西亚难民的医疗基金。当一位难民母亲——或一个面对医疗紧急状况的家庭——需要在数小时内筹到钱时，我们直接向医院付款，陪伴这个家庭走完整个流程，并公开每一分令吉。',
    'hero.cta1': '立即捐款',
    'hero.cta2': '赞助一次安全分娩 — RM 2,000',
    'hero.help': '家人需要医疗援助？<a href="#contact">联系我们的热线&nbsp;→</a>',

    'banner': '<strong>直付医院，绝不经手现金。</strong>&ensp;每个个案三次进展通报——<em>已筹款 → 已治疗 → 已康复</em>——每一分令吉都记入公开账目。',

    'need.h2': 'Sutera Cares 为何存在',
    'need.stat1': '名难民与寻求庇护者在马来西亚向联合国难民署（UNHCR）登记——他们没有合法身份、没有工作权，也不在公共医疗保障之内。',
    'need.stat2': '是医院在分娩前可能要求的按金——往往相当于一个难民家庭三个月的收入。',
    'need.stat3num': '24–48 小时',
    'need.stat3': '是 Sutera Cares 从接到紧急转介到做出资助决定所需的时间——因为紧急情况需要的是几小时，而不是几星期。',
    'need.note': '在政府医院，难民须按「外国人」收费。联合国难民署的证件可减免一半费用，但一次分娩仍可能花费数千令吉——而未付清的账单会带来债务、日后被拒诊的风险，以及恐惧。怀孕是难民家庭面对巨额医疗账单最常见的原因，紧急状况则最具毁灭性。服务难民的诊所是有的；一直缺少的，是一条由社区主导、能够迅速<em>把账单付掉并陪着家庭走过去</em>的途径。这正是我们要填补的缺口。',

    'prog.h2': '三项计划，一个承诺',
    'prog.lead': '我们不开设诊所，也从不发放现金。我们承担医疗费用、协助家庭应对就医流程，其余需求则转介给可信赖的伙伴机构。',
    'prog.c1h': 'Sutera 紧急基金',
    'prog.c1p': '为危及生命的个案在当天支付按金与账单——并协助申请费用减免、与医院社会工作部门商议分期付款。',
    'prog.c1amt': '<strong>RM 5,000</strong> 可资助一次紧急救援',
    'prog.c2h': 'Sutera 安全分娩',
    'prog.c2p': '产前检查、伙伴诊所与政府医院的分娩费用、产后与新生儿跟进、母婴用品包，以及协助办理出生登记。',
    'prog.c2amt': '<strong>RM 2,000</strong> 可赞助一次安全分娩',
    'prog.c3h': '就医陪伴',
    'prog.c3p': '一条热线，以及会说家庭母语的陪伴员——口译、陪同就医、文件处理与转介。那个陪她一起走进医院的人。',
    'prog.c3amt': '<strong>每月 RM 20 起</strong> — Sutera Circle 支持陪伴员的工作',

    'how.h2': '一个个案如何处理',
    'how.s1h': '来电',
    'how.s1p': '家庭、社区领袖、诊所或医院社工透过 WhatsApp 热线联系我们——可使用马来语、英语、罗兴亚语、缅甸语或阿拉伯语。',
    'how.s2h': '核实',
    'how.s2p': '我们查核联合国难民署证件、医疗文件与医院开出的报价，并向社区领袖求证——紧急个案在24小时内完成。',
    'how.s3h': '筹款',
    'how.s3p': '由紧急基金或指定募款项目承担账单。款项直接付给医院或诊所——绝不经手现金，每一张收据都会公开。',
    'how.s4h': '陪伴',
    'how.s4p': '陪伴员全程陪同家庭办理入院、治疗与文件手续——包括新生儿的出生证明与联合国难民署登记。',
    'how.s5h': '跟进',
    'how.s5p': '在第7天与第6周进行产后与康复检查。个案随结果结案，每位捐款人都会收到第三次通报：<em>已康复</em>。',

    'promises.h2': '我们的承诺——公开写明的规则',
    'promises.p1': '<strong>只直付医院。</strong> 我们把钱付给诊所与医院，从不把现金交到任何人手上。',
    'promises.p2': '<strong>募款项目的捐款100%用于病人。</strong> 运营开支另由 Sutera Circle 每月捐款人承担。',
    'promises.p3': '<strong>每个个案都记入公开账目。</strong> 筹得金额、支付金额、收据——同时保护病人隐私。',
    'promises.p4': '<strong>每个个案三次通报。</strong> 已筹款 → 已治疗 → 已康复，通报给每一位捐款人。',
    'promises.p5': '<strong>结余绝不悄悄留下。</strong> 若募款超出账单金额，余款转入紧急基金——这一点写在每一个募款页面上。',
    'promises.p6': '<strong>每笔付款须两人批准。</strong> 独立财政、每月对账、每季公开财务报告。',
    'promises.ledger': '<strong>即时个案账目</strong>——我们的首批个案将在此列出，逐一显示筹得金额、支付给医院的金额，以及收据。',

    'donate.h2': '捐助方式',
    'donate.lead': '每一项捐助都对应一笔真实的成本——这正是它可信的原因。',
    'donate.d1h': '母婴用品包',
    'donate.d1p': '新生儿必需品与第一次产后探访。',
    'donate.d2h': '产前照护配套',
    'donate.d2p': '4至6次诊所检查、化验、超声波与营养补充。',
    'donate.d3h': '赞助一次安全分娩',
    'donate.d3p': '一次从头到尾获得支持的分娩。',
    'donate.d4h': '紧急救援',
    'donate.d4p': '一次紧急入院或剖腹产。',
    'donate.d5amt': '每月 RM 20 起',
    'donate.d5h': 'Sutera Circle',
    'donate.d5p': '每月捐款，支持陪伴员、热线与交通。',
    'donate.d6amt': 'Zakat 天课',
    'donate.d6h': '天课专款',
    'donate.d6p': '专款专用于符合资格的受惠者。一般捐款则不分宗教，服务所有人。',
    'donate.qrh': 'DuitNow QR',
    'donate.qr': '二维码<br>将随首个募款项目<br>一并推出',
    'donate.bankh': '银行转账',
    'donate.bank': '[伙伴机构账户名称]<br>[银行 · 账号]<br>注明：<strong>SUTRA</strong>',
    'donate.onlineh': '线上捐款页面',
    'donate.online': '信用卡与 FPX 捐款页面——即将推出。',
    'donate.note': '捐款由我们在马来西亚注册的伙伴机构接收，并直接支付给医疗服务提供者。捐款<strong>目前尚不可扣税</strong>——我们把话说清楚，一旦情况改变，会立即告知。',

    'about.h2': '我们是谁',
    'about.p1': '马来语的 <em>sutera</em>（丝绸）源自梵文的 <em>sutra</em>，意思是「线」。<strong>那根承托一切的线：柔如丝，韧如命脉。</strong>',
    'about.p2': 'Sutera Cares 由一位在马来西亚难民社群中深受信任、联系广泛的难民社区领袖，与一位在本地社区、清真寺与机构间享有声望的马来西亚联合创办人共同创立——这个问题需要的，正是这两半。',
    'about.p3': '本基金由一个独立的马来西亚委员会管理，成员包括医生、会计师与律师；另设由难民领袖组成的社区咨询理事会，确保工作始终由社区主导。在我们自己的社团注册获批之前，我们在一家已注册的马来西亚伙伴机构之下运作。',
    'about.p4': '我们服务任何难民或寻求庇护者——不分族裔、不分信仰——从巴生谷开始。',
    'about.cardh': '我们刻意不做的事',
    'about.li1': '不开设诊所——我们在现有诊所资助并协助就医。',
    'about.li2': '不发现金给病人——款项付给医疗服务提供者。',
    'about.li3': '不重复其他非政府组织的工作——我们补上资金缺口，其余转介。',
    'about.li4': '不提供贷款——援助是赠予，绝非债务。',
    'about.li5': '未经同意，绝不公开任何人的面孔、姓名或住址。',

    'partners.h2': '与诊所、医院及非政府组织合作',
    'partners.note': '我们正在与伙伴诊所和医院商定合作条款。协议签署后，他们的名字才会出现在这里——我们只展示真实的事。<br><a href="#contact">经营诊所或非政府组织？欢迎与我们洽谈合作&nbsp;→</a>',

    'vol.h2': 'Sutera 靠志愿者运转',
    'vol.p': '陪伴员、口译员、医护、内容撰写者、筹款人——每周两小时，就能改变一个人最难熬的日子。',
    'vol.btn': '成为志愿者',

    'faq.h2': '捐款人常问的问题',
    'faq.q1': '马来西亚人也需要帮助，为什么要帮难民？',
    'faq.a1': '生活困难的马来西亚人有政府援助计划与补贴医疗；难民什么都没有。爱心不是此消彼长——我们许多捐款人同时也支持本地公益，我们由衷支持他们这么做。',
    'faq.q2': '这合法吗？',
    'faq.a2': '合法。捐款由已注册的机构接收——目前是我们的马来西亚伙伴机构，待批准后则是我们自己的社团——并直接支付给医院与诊所。',
    'faq.q3': '我的捐款可以扣税吗？',
    'faq.a3': '目前还不行。在马来西亚取得免税资格，大约需要两年的经审计账目。若某笔捐款可经由已获批准的伙伴机构处理，我们会清楚说明。',
    'faq.q4': '我的钱去了哪里？',
    'faq.a4': '募款项目的捐款100%用于病人；运营开支由 Sutera Circle 每月捐款人承担。每个个案都会连同收据列入公开账目。',
    'faq.q5': '你们如何确认个案属实？',
    'faq.a5': '联合国难民署证件、医疗文件、医院开出的报价、社区领袖的核实——付款前我们还会向医院再次确认。',
    'faq.q6': '如果募得的款项超过个案所需呢？',
    'faq.a6': '余款转入紧急基金，用于下一个家庭。这句话写在每一个募款页面上。',
    'faq.q7': '我可以捐天课（zakat）吗？',
    'faq.a7': '可以——捐入专用于符合资格受惠者的天课专款。一般捐款则服务每一位难民，不分信仰。',
    'faq.q8': '我的公司可以参与吗？',
    'faq.a8': '可以：安全分娩赞助配套（RM 10,000 赞助五次分娩，并提供具名的成果报告）、母婴用品包活动日，以及由贵公司团队担任的志愿陪伴员。',

    'contact.h2': '联络我们',
    'contact.c1h': 'WhatsApp 热线',
    'contact.c1p': '每日早上8点至晚上10点 · 马来语、英语、罗兴亚语、缅甸语、阿拉伯语',
    'contact.c2h': '电邮',
    'contact.c2p': '捐款人、合作伙伴、志愿者与媒体。',
    'contact.c3h': '服务地区',
    'contact.c3big': '马来西亚巴生谷',
    'contact.c3p': '槟城与柔佛可经转介安排。',
    'contact.emergency': '<strong>遇到危及生命的紧急情况，请先前往最近的医院急诊部——先治疗，账单的事之后再说。</strong> 之后再联系我们，账单交给我们处理。',

    'footer.mission': 'Sutera Cares — 那根承托一切的线。为马来西亚的难民母亲与紧急医疗个案，提供及时、有温度且负责任的医疗援助。',
    'footer.legal': '捐款由我们在马来西亚注册的伙伴机构接收，并直接支付给医疗服务提供者；社团注册正在办理中。捐款目前尚不可扣税。未经同意，我们绝不公开病人的姓名、面孔或住址。',
    'footer.copy': '© 2026 Sutera Cares · 马来西亚吉隆坡'
  };

  var DICTS = { en: null, ms: ms, zh: zh };

  /* ---------------------------------------------------------
     Engine
     --------------------------------------------------------- */

  var textNodes = [];   /* [{ el, key }]        — innerHTML targets */
  var labelNodes = [];  /* [{ el, key }]        — aria-label targets */
  var en = {};          /* the English page, captured on load */
  var canonicalBase = '';

  function meta(attr, value) {
    return document.head.querySelector('meta[' + attr + '="' + value + '"]');
  }

  function canonicalLink() {
    return document.head.querySelector('link[rel="canonical"]');
  }

  function capture() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-i18n]'), function (el) {
      var key = el.getAttribute('data-i18n');
      textNodes.push({ el: el, key: key });
      en[key] = el.innerHTML;
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-i18n-label]'), function (el) {
      var key = el.getAttribute('data-i18n-label');
      labelNodes.push({ el: el, key: key });
      en[key] = el.getAttribute('aria-label');
    });
    en['meta.title'] = document.title;
    en['meta.desc'] = meta('name', 'description').content;
    en['meta.ogTitle'] = meta('property', 'og:title').content;
    en['meta.ogDesc'] = meta('property', 'og:description').content;
    DICTS.en = en;

    /* The site's real address, taken from the canonical tag so the
       domain is written in exactly one place (index.html). */
    var canon = canonicalLink();
    canonicalBase = canon ? canon.href : window.location.origin + window.location.pathname;
  }

  /* Falls back to English whenever a key is missing from a translation,
     so an untranslated string shows in English rather than vanishing. */
  function stringFor(dict, key) {
    return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : en[key];
  }

  function apply(lang) {
    var dict = DICTS[lang] || en;

    textNodes.forEach(function (n) { n.el.innerHTML = stringFor(dict, n.key); });
    labelNodes.forEach(function (n) { n.el.setAttribute('aria-label', stringFor(dict, n.key)); });

    document.title = stringFor(dict, 'meta.title');
    meta('name', 'description').content = stringFor(dict, 'meta.desc');
    meta('property', 'og:title').content = stringFor(dict, 'meta.ogTitle');
    meta('property', 'og:description').content = stringFor(dict, 'meta.ogDesc');

    document.documentElement.setAttribute('lang', HTML_LANG[lang] || lang);

    /* Point canonical / og:url at the version actually being shown. */
    var url = canonicalBase + (lang === DEFAULT_LANG ? '' : '?lang=' + lang);
    var canon = canonicalLink();
    if (canon) canon.href = url;
    var ogUrl = meta('property', 'og:url');
    if (ogUrl) ogUrl.content = url;

    Array.prototype.forEach.call(document.querySelectorAll('.lang-switch button'), function (btn) {
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

  /* Keeps the address bar shareable: ?lang=ms points someone
     straight at the Malay page, without reloading it here. */
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
    setLanguage(pick());

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
