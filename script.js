document.addEventListener('DOMContentLoaded', () => {
    const viewReportBtn = document.getElementById('viewReportBtn');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    const reportPreview = document.getElementById('reportPreview');
    const previewContent = document.getElementById('previewContent');

    let lastBlobUrl = null;
    let logoDataUrl = null;
    let logoAspect = 1; // width / height
    let ttdDataUrl = null;
    let ttdAspect = 1;

    const loadImageAsDataUrl = (src) => new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            canvas.getContext('2d').drawImage(img, 0, 0);
            try {
                resolve({ dataUrl: canvas.toDataURL('image/png'), aspect: img.naturalWidth / img.naturalHeight });
            } catch (e) {
                resolve({ dataUrl: null, aspect: 1 });
            }
        };
        img.onerror = () => resolve({ dataUrl: null, aspect: 1 });
        img.src = src;
    });

    loadImageAsDataUrl('logo.png').then(r => { logoDataUrl = r.dataUrl; logoAspect = r.aspect; });
    loadImageAsDataUrl('ttd.png').then(r => { ttdDataUrl = r.dataUrl; ttdAspect = r.aspect; });

    const getFormData = () => {
        return {
            kepada: document.getElementById('kepada').value,
            perihalSurat: document.getElementById('perihalSurat').value,
            namaKegiatan: document.getElementById('namaKegiatan').value,
            tanggalWaktuRapat: document.getElementById('tanggalWaktuRapat').value,
            tempatRapat: document.getElementById('tempatRapat').value,
            pimpinanRapat: document.getElementById('pimpinanRapat').value,
            pesertaRapat: document.getElementById('pesertaRapat').value,
            hasilPembahasan: document.getElementById('hasilPembahasan').value,
            kesimpulanSaranRTL: document.getElementById('kesimpulanSaranRTL').value,
        };
    };

    const isComplete = (d) => d.kepada && d.perihalSurat && d.namaKegiatan && d.tanggalWaktuRapat && d.tempatRapat && d.pimpinanRapat && d.pesertaRapat && d.hasilPembahasan && d.kesimpulanSaranRTL;

    const buildPdf = (d) => {
        const dateObj = new Date(d.tanggalWaktuRapat);
        const formattedDate = dateObj.toLocaleString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const formattedTime = dateObj.toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit' });

        const pesertaItems = d.pesertaRapat.split('\n').map(i => i.trim()).filter(i => i);
        const hasilItems = d.hasilPembahasan.split('\n').map(i => i.trim()).filter(i => i);
        const kesimpulanItems = d.kesimpulanSaranRTL.split('\n').map(i => i.trim()).filter(i => i);

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFont('helvetica', 'normal');

        const margin = 15;
        const lineHeight = 6;
        const pageHeight = doc.internal.pageSize.height;
        const pageWidth = doc.internal.pageSize.width;
        const contentWidth = pageWidth - 2 * margin;
        let yPos = margin;
        let pageNumber = 1;

        const ensureSpace = (needed) => {
            if (yPos + needed > pageHeight - margin - 6) {
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.text(`Halaman ${pageNumber}`, pageWidth - margin, pageHeight - margin, { align: 'right' });
                doc.addPage();
                pageNumber++;
                yPos = margin;
            }
        };

        // Kop Surat
        const kopTop = yPos;
        const logoHeight = 24;
        const logoWidth = logoHeight * (logoAspect || 1);
        const logoX = margin + 2;
        const logoY = kopTop + 1;
        if (logoDataUrl) {
            try { doc.addImage(logoDataUrl, 'PNG', logoX, logoY, logoWidth, logoHeight); } catch (e) {}
        }

        // Center teks pada area di kanan logo (biar terlihat benar-benar di tengah blok kop)
        const textLeft = logoX + logoWidth + 4;
        const textRight = pageWidth - margin;
        const kopCenterX = (textLeft + textRight) / 2;
        let kopY = kopTop + 5;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(13);
        doc.text('PEMERINTAH KABUPATEN KEDIRI', kopCenterX, kopY, { align: 'center' });
        kopY += 6.5;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(15);
        doc.text('DINAS KOMUNIKASI DAN INFORMATIKA', kopCenterX, kopY, { align: 'center' });
        kopY += 6;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.text('Jalan Sekartaji Nomor 2 Ds. Doko Kec. Ngasem 64182', kopCenterX, kopY, { align: 'center' });
        kopY += 4.5;
        doc.text('Telp. (0354) 682152. Pos-el diskominfo@kedirikab.go.id', kopCenterX, kopY, { align: 'center' });
        kopY += 4.5;
        doc.text('Laman www.diskominfo.kedirikab.go.id', kopCenterX, kopY, { align: 'center' });
        kopY += 6;

        const kopBottom = Math.max(kopY, logoY + logoHeight + 3);
        doc.setLineWidth(1.0);
        doc.line(margin, kopBottom, pageWidth - margin, kopBottom);
        doc.setLineWidth(0.3);
        doc.line(margin, kopBottom + 1.2, pageWidth - margin, kopBottom + 1.2);

        yPos = kopBottom + 14;

        // Title
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(13);
        const title = 'LAPORAN HASIL KEGIATAN';
        doc.text(title, pageWidth / 2, yPos, { align: 'center' });
        doc.setLineWidth(0.4);
        doc.line(pageWidth / 2 - 35, yPos + 1.5, pageWidth / 2 + 35, yPos + 1.5);
        yPos += 10;

        // Header fields
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(11);
        const labelWidth = 33;
        // labelBold / valueBold controls weight per field
        // colonAligned=false → titik dua menempel di label (gap kecil), tanpa mengikuti kolom labelWidth
        const writeField = (label, value, labelBold, valueBold, colonAligned = true) => {
            doc.setFont('helvetica', labelBold ? 'bold' : 'normal');
            const labelTextWidth = doc.getTextWidth(label);
            const colonX = colonAligned ? margin + labelWidth : margin + labelTextWidth + 1.5;
            const valueX = colonX + 3;
            const valueLines = doc.splitTextToSize(String(value), contentWidth - (valueX - margin));
            ensureSpace(lineHeight * valueLines.length);
            doc.text(label, margin, yPos);
            doc.text(':', colonX, yPos);
            doc.setFont('helvetica', valueBold ? 'bold' : 'normal');
            valueLines.forEach((line, idx) => {
                doc.text(line, valueX, yPos + idx * lineHeight);
            });
            yPos += lineHeight * valueLines.length;
        };

        // Titik dua "Kepada" & "Perihal" sejajar hanya di antara keduanya
        const headerLabelWidth = Math.max(doc.getTextWidth('Kepada'), doc.getTextWidth('Perihal')) + 1.5;
        const writeHeaderField = (label, value, valueBold) => {
            doc.setFont('helvetica', 'normal');
            const colonX = margin + headerLabelWidth;
            const valueX = colonX + 3;
            const valueLines = doc.splitTextToSize(String(value), contentWidth - (valueX - margin));
            ensureSpace(lineHeight * valueLines.length);
            doc.text(label, margin, yPos);
            doc.text(':', colonX, yPos);
            doc.setFont('helvetica', valueBold ? 'bold' : 'normal');
            valueLines.forEach((line, idx) => {
                doc.text(line, valueX, yPos + idx * lineHeight);
            });
            yPos += lineHeight * valueLines.length;
        };

        writeHeaderField('Kepada',  d.kepada,       true);
        writeHeaderField('Perihal', d.perihalSurat, false);
        writeField('Nama Kegiatan',  d.namaKegiatan,         true,  false, true);
        writeField('Hari/Tanggal',   formattedDate,          true,  false, true);
        writeField('Pukul',          formattedTime + ' WIB', true,  false, true);
        writeField('Tempat',         d.tempatRapat,          true,  false, true);
        writeField('Pimpinan Rapat', d.pimpinanRapat,        true,  false, true);
        yPos += lineHeight;

        const toRoman = (num) => {
            const map = [
                ['M', 1000], ['CM', 900], ['D', 500], ['CD', 400],
                ['C', 100],  ['XC', 90],  ['L', 50],  ['XL', 40],
                ['X', 10],   ['IX', 9],   ['V', 5],   ['IV', 4],
                ['I', 1],
            ];
            let result = '';
            for (const [sym, val] of map) {
                while (num >= val) { result += sym; num -= val; }
            }
            return result;
        };

        const writeNumberedSection = (heading, items) => {
            ensureSpace(lineHeight * 2);
            doc.setFont('helvetica', 'bold');
            doc.text(heading, margin, yPos);
            yPos += lineHeight;
            doc.setFont('helvetica', 'normal');

            // Posisi angka: sejajar dengan huruf pertama heading (setelah "I. ")
            doc.setFont('helvetica', 'bold');
            const headingPrefix = heading.split(' ')[0] + ' '; // mis. "I. "
            const numberIndent = doc.getTextWidth(headingPrefix);
            doc.setFont('helvetica', 'normal');
            const textIndent = numberIndent + 6;

            items.forEach((item, idx) => {
                const number = `${idx + 1}.`;
                const lines = doc.splitTextToSize(item, contentWidth - textIndent);
                ensureSpace(lineHeight * lines.length);
                doc.text(number, margin + numberIndent, yPos);
                lines.forEach((line, i) => {
                    doc.text(line, margin + textIndent, yPos + i * lineHeight);
                });
                yPos += lineHeight * lines.length;
            });
            yPos += lineHeight;
        };

        writeNumberedSection(`${toRoman(1)}. PESERTA RAPAT`, pesertaItems);
        writeNumberedSection(`${toRoman(2)}. HASIL PEMBAHASAN`, hasilItems);
        writeNumberedSection(`${toRoman(3)}. KESIMPULAN/SARAN/RTL`, kesimpulanItems);

        // Penutup
        const today = new Date();
        const todayFormatted = today.toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });

        const ttdBlockHeight = 60; // perkiraan tinggi blok ttd + penutup
        ensureSpace(ttdBlockHeight);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.text('Penutup', margin, yPos);
        yPos += lineHeight;
        doc.setFont('helvetica', 'normal');
        doc.text('Demikian untuk menjadikan periksa.', margin, yPos);
        yPos += lineHeight * 2;

        // Blok tanda tangan di kanan
        const ttdLeft = pageWidth / 2 + 10;
        let ttdY = yPos;
        doc.setFont('helvetica', 'normal');
        doc.text(`Dibuat di  : Kediri, ${todayFormatted}`, ttdLeft, ttdY);
        ttdY += lineHeight;
        doc.text('Kepala Bidang Statistik', ttdLeft, ttdY);
        ttdY += lineHeight;
        doc.text('Dinas Kominfo Kabupaten Kediri', ttdLeft, ttdY);
        ttdY += lineHeight;

        // Gambar tanda tangan jika ada
        if (ttdDataUrl) {
            const ttdH = 22;
            const ttdW = ttdH * (ttdAspect || 1);
            try { doc.addImage(ttdDataUrl, 'PNG', ttdLeft, ttdY + 1, ttdW, ttdH); } catch (e) {}
            ttdY += ttdH + 2;
        } else {
            ttdY += 22; // ruang kosong untuk ttd manual
        }

        // Nama (bold + underline)
        doc.setFont('helvetica', 'bold');
        const namaPejabat = 'Nadlirin, S.H';
        doc.text(namaPejabat, ttdLeft, ttdY);
        const namaWidth = doc.getTextWidth(namaPejabat);
        doc.setLineWidth(0.3);
        doc.line(ttdLeft, ttdY + 1, ttdLeft + namaWidth, ttdY + 1);
        ttdY += lineHeight;

        doc.setFont('helvetica', 'normal');
        doc.text('Penata Tk.I/III-d', ttdLeft, ttdY);
        ttdY += lineHeight;
        doc.text('NIP. 198306162011011005', ttdLeft, ttdY);
        ttdY += lineHeight;

        yPos = ttdY;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text(`Halaman ${pageNumber}`, pageWidth - margin, pageHeight - margin, { align: 'right' });

        return doc;
    };

    const isMobileDevice = () => /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

    const renderPdfToIframe = () => {
        previewContent.innerHTML = `
            <iframe
                src="${lastBlobUrl}#toolbar=1&navpanes=0&view=FitH"
                style="width:100%; height:80vh; border:none; background:#525659;"
                title="Pratinjau Laporan PDF">
            </iframe>
        `;
    };

    const renderFallbackLink = () => {
        previewContent.innerHTML = `
            <div style="padding:20px; text-align:center;">
                <p style="color:#ddd; margin-bottom:12px;">Pratinjau tidak tersedia di perangkat ini.</p>
                <a href="${lastBlobUrl}" target="_blank" rel="noopener"
                   style="display:inline-block; padding:10px 20px; background:#4a90e2; color:#fff; text-decoration:none; border-radius:5px;">
                   Buka Laporan di Tab Baru
                </a>
            </div>
        `;
    };

    const renderPdfToCanvas = async (arrayBuffer) => {
        if (!window.pdfjsLib) throw new Error('pdfjsLib not loaded');
        const pdf = await window.pdfjsLib.getDocument({ data: arrayBuffer }).promise;

        const container = document.createElement('div');
        container.style.cssText = 'width:100%; max-height:80vh; overflow-y:auto; background:#525659; padding:10px; box-sizing:border-box;';

        const containerWidth = previewContent.clientWidth - 20;

        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const viewport = page.getViewport({ scale: 1 });
            const scale = containerWidth / viewport.width;
            const scaledViewport = page.getViewport({ scale: scale * (window.devicePixelRatio || 1) });

            const canvas = document.createElement('canvas');
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            canvas.style.width = (scaledViewport.width / (window.devicePixelRatio || 1)) + 'px';
            canvas.style.height = (scaledViewport.height / (window.devicePixelRatio || 1)) + 'px';
            canvas.style.display = 'block';
            canvas.style.margin = '0 auto 10px auto';
            canvas.style.boxShadow = '0 2px 8px rgba(0,0,0,0.4)';

            await page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledViewport }).promise;
            container.appendChild(canvas);
        }

        previewContent.innerHTML = '';
        previewContent.appendChild(container);
    };

    viewReportBtn.addEventListener('click', async () => {
        const d = getFormData();
        if (!isComplete(d)) {
            alert('Mohon lengkapi semua kolom sebelum melihat pratinjau.');
            exportPdfBtn.style.display = 'none';
            reportPreview.style.display = 'none';
            return;
        }

        const doc = buildPdf(d);
        const blob = doc.output('blob');

        if (lastBlobUrl) URL.revokeObjectURL(lastBlobUrl);
        lastBlobUrl = URL.createObjectURL(blob);

        reportPreview.style.display = 'block';
        exportPdfBtn.style.display = 'block';
        previewContent.innerHTML = '<p style="color:#ddd; text-align:center; padding:20px;">Memuat pratinjau...</p>';

        if (isMobileDevice()) {
            try {
                const arrayBuffer = await blob.arrayBuffer();
                await renderPdfToCanvas(arrayBuffer);
            } catch (err) {
                console.error('PDF.js render gagal di mobile, fallback ke tombol:', err);
                renderFallbackLink();
            }
        } else {
            try {
                renderPdfToIframe();
            } catch (err) {
                console.error('Iframe render gagal di desktop, fallback ke PDF.js:', err);
                try {
                    const arrayBuffer = await blob.arrayBuffer();
                    await renderPdfToCanvas(arrayBuffer);
                } catch (err2) {
                    console.error('PDF.js fallback juga gagal:', err2);
                    renderFallbackLink();
                }
            }
        }
    });

    exportPdfBtn.addEventListener('click', () => {
        const d = getFormData();
        if (!isComplete(d)) {
            alert('Mohon lengkapi semua kolom sebelum mengekspor laporan.');
            return;
        }
        const doc = buildPdf(d);
        const filename = `Laporan_${d.perihalSurat.replace(/\s/g, '_')}_${new Date().getTime()}.pdf`;
        doc.save(filename);
        alert('Laporan berhasil diekspor sebagai PDF!');
    });
});
