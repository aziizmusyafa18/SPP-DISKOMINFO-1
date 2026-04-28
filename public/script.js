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
            namaPelapor: document.getElementById('namaPelapor').value,
            kepada: document.getElementById('kepada').value,
            perihalSurat: document.getElementById('perihalSurat').value,
            namaKegiatan: document.getElementById('namaKegiatan').value,
            tanggalWaktuRapat: document.getElementById('tanggalWaktuRapat').value,
            tanggalWaktuSelesai: document.getElementById('tanggalWaktuSelesai').value,
            tempatRapat: document.getElementById('tempatRapat').value,
            pimpinanRapat: document.getElementById('pimpinanRapat').value,
            pesertaRapat: document.getElementById('pesertaRapat').value,
            hasilPembahasan: document.getElementById('hasilPembahasan').value,
            kesimpulanSaranRTL: document.getElementById('kesimpulanSaranRTL').value,
        };
    };

    const isComplete = (d) => d.namaPelapor && d.kepada && d.perihalSurat && d.namaKegiatan && d.tanggalWaktuRapat && d.tanggalWaktuSelesai && d.tempatRapat && d.pimpinanRapat && d.pesertaRapat && d.hasilPembahasan && d.kesimpulanSaranRTL;

    const buildPdf = (d) => {
        const startObj = new Date(d.tanggalWaktuRapat);
        const endObj = new Date(d.tanggalWaktuSelesai);
        
        const formattedDateStart = startObj.toLocaleString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const formattedTimeStart = startObj.toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit' });
        
        const formattedDateEnd = endObj.toLocaleString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const formattedTimeEnd = endObj.toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit' });

        const isSameDay = startObj.toDateString() === endObj.toDateString();

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

        const headerLabelWidth = Math.max(
            doc.getTextWidth('Nama Pelapor'),
            doc.getTextWidth('Kepada'), 
            doc.getTextWidth('Perihal')
        ) + 1.5;
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

        writeHeaderField('Nama Pelapor', d.namaPelapor, true);
        writeHeaderField('Kepada',  d.kepada,       true);
        writeHeaderField('Perihal', d.perihalSurat, false);
        writeField('Nama Kegiatan',  d.namaKegiatan,         true,  false, true);
        
        if (isSameDay) {
            writeField('Hari/Tanggal',   formattedDateStart,          true,  false, true);
            writeField('Pukul',          `${formattedTimeStart} s/d ${formattedTimeEnd} WIB`, true,  false, true);
        } else {
            writeField('Waktu Mulai',    `${formattedDateStart}, Pukul ${formattedTimeStart} WIB`, true,  false, true);
            writeField('Waktu Selesai',  `${formattedDateEnd}, Pukul ${formattedTimeEnd} WIB`, true,  false, true);
        }

        writeField('Tempat',         d.tempatRapat,          true,  false, true);
        writeField('Pimpinan Rapat', d.pimpinanRapat,        true,  false, true);
        yPos += lineHeight;

        const toRoman = (num) => {
            const map = [['M', 1000], ['CM', 900], ['D', 500], ['CD', 400], ['C', 100], ['XC', 90], ['L', 50], ['XL', 40], ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1]];
            let result = '';
            for (const [sym, val] of map) { while (num >= val) { result += sym; num -= val; } }
            return result;
        };

        const writeNumberedSection = (heading, items) => {
            ensureSpace(lineHeight * 2);
            doc.setFont('helvetica', 'bold');
            doc.text(heading, margin, yPos);
            yPos += lineHeight;
            doc.setFont('helvetica', 'normal');
            doc.setFont('helvetica', 'bold');
            const headingPrefix = heading.split(' ')[0] + ' ';
            const numberIndent = doc.getTextWidth(headingPrefix);
            doc.setFont('helvetica', 'normal');
            const textIndent = numberIndent + 6;
            items.forEach((item, idx) => {
                const number = `${idx + 1}.`;
                const lines = doc.splitTextToSize(item, contentWidth - textIndent);
                ensureSpace(lineHeight * lines.length);
                doc.text(number, margin + numberIndent, yPos);
                lines.forEach((line, i) => { doc.text(line, margin + textIndent, yPos + i * lineHeight); });
                yPos += lineHeight * lines.length;
            });
            yPos += lineHeight;
        };

        writeNumberedSection(`${toRoman(1)}. PESERTA RAPAT`, pesertaItems);
        writeNumberedSection(`${toRoman(2)}. HASIL PEMBAHASAN`, hasilItems);
        writeNumberedSection(`${toRoman(3)}. KESIMPULAN/SARAN/RTL`, kesimpulanItems);

        const today = new Date();
        const todayFormatted = today.toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
        ensureSpace(60);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.text('Penutup', margin, yPos);
        yPos += lineHeight;
        doc.setFont('helvetica', 'normal');
        doc.text('Demikian untuk menjadikan periksa.', margin, yPos);
        yPos += lineHeight * 2;
        const ttdLeft = pageWidth / 2 + 10;
        let ttdY = yPos;
        doc.text(`Dibuat di  : Kediri, ${todayFormatted}`, ttdLeft, ttdY);
        ttdY += lineHeight;
        doc.text('Kepala Bidang Statistik', ttdLeft, ttdY);
        ttdY += lineHeight;
        doc.text('Dinas Kominfo Kabupaten Kediri', ttdLeft, ttdY);
        ttdY += lineHeight;
        if (ttdDataUrl) {
            const ttdH = 22;
            const ttdW = ttdH * (ttdAspect || 1);
            try { doc.addImage(ttdDataUrl, 'PNG', ttdLeft, ttdY + 1, ttdW, ttdH); } catch (e) {}
            ttdY += ttdH + 2;
        } else { ttdY += 22; }
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
        doc.setFontSize(9);
        doc.text(`Halaman ${pageNumber}`, pageWidth - margin, pageHeight - margin, { align: 'right' });
        return doc;
    };

    const isMobileDevice = () => /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
    const renderPdfToIframe = () => { previewContent.innerHTML = `<iframe src="${lastBlobUrl}#toolbar=1&navpanes=0&view=FitH" style="width:100%; height:80vh; border:none; background:#525659;" title="Pratinjau Laporan PDF"></iframe>`; };
    const renderFallbackLink = () => { previewContent.innerHTML = `<div style="padding:20px; text-align:center;"><p style="color:#ddd; margin-bottom:12px;">Pratinjau tidak tersedia di perangkat ini.</p><a href="${lastBlobUrl}" target="_blank" rel="noopener" style="display:inline-block; padding:10px 20px; background:#4a90e2; color:#fff; text-decoration:none; border-radius:5px;">Buka Laporan di Tab Baru</a></div>`; };
    const renderPdfToCanvas = async (arrayBuffer) => {
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
            canvas.width = scaledViewport.width; canvas.height = scaledViewport.height;
            canvas.style.width = (scaledViewport.width / (window.devicePixelRatio || 1)) + 'px';
            canvas.style.height = (scaledViewport.height / (window.devicePixelRatio || 1)) + 'px';
            canvas.style.display = 'block'; canvas.style.margin = '0 auto 10px auto'; canvas.style.boxShadow = '0 2px 8px rgba(0,0,0,0.4)';
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledViewport }).promise;
            container.appendChild(canvas);
        }
        previewContent.innerHTML = ''; previewContent.appendChild(container);
    };

    viewReportBtn.addEventListener('click', async () => {
        const d = getFormData();
        if (!isComplete(d)) { alert('Mohon lengkapi semua kolom sebelum melihat pratinjau.'); return; }
        const doc = buildPdf(d);
        const blob = doc.output('blob');
        if (lastBlobUrl) URL.revokeObjectURL(lastBlobUrl);
        lastBlobUrl = URL.createObjectURL(blob);
        reportPreview.style.display = 'block'; exportPdfBtn.style.display = 'block';
        previewContent.innerHTML = '<p style="color:#ddd; text-align:center; padding:20px;">Memuat pratinjau...</p>';
        if (isMobileDevice()) { try { await renderPdfToCanvas(await blob.arrayBuffer()); } catch (err) { renderFallbackLink(); } }
        else { try { renderPdfToIframe(); } catch (err) { try { await renderPdfToCanvas(await blob.arrayBuffer()); } catch (err2) { renderFallbackLink(); } } }
    });

    const saveLaporanToServer = async (d, blob, filename) => {
        const formData = new FormData();
        Object.keys(d).forEach(k => formData.append(k, d[k]));
        formData.append('filename', filename);
        formData.append('pdf', blob, filename);
        const res = await fetch('../api/save_laporan.php', { method: 'POST', body: formData });
        const result = await res.json().catch(() => ({ ok: false, error: 'Respon server tidak valid.' }));
        if (!res.ok || !result.ok) throw new Error(result.error || `HTTP ${res.status}`);
        return result;
    };

    exportPdfBtn.addEventListener('click', async () => {
        const d = getFormData();
        if (!isComplete(d)) { alert('Mohon lengkapi semua kolom.'); return; }
        const doc = buildPdf(d);
        const filename = `Laporan_${d.perihalSurat.replace(/\s/g, '_')}_${new Date().getTime()}.pdf`;
        doc.save(filename);
        exportPdfBtn.disabled = true; exportPdfBtn.textContent = 'Menyimpan...';
        try {
            await saveLaporanToServer(d, doc.output('blob'), filename);
            alert('Laporan berhasil diekspor dan tersimpan.');
        } catch (err) { alert('Gagal menyimpan ke database: ' + err.message); }
        finally { exportPdfBtn.disabled = false; exportPdfBtn.textContent = 'Export PDF'; }
    });
});
