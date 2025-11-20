/**
 * Yayın Formu JavaScript
 * 
 * Yayın türüne göre ilgili form alanlarını gösterir/gizler
 */

document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type_id');
    
    if (typeSelect) {
        // Sayfa yüklendiğinde seçili türü kontrol et
        handleTypeChange();
        
        // Tür değiştiğinde
        typeSelect.addEventListener('change', handleTypeChange);
    }
    
    /**
     * Yayın türü değiştiğinde ilgili bölümleri göster/gizle
     */
    function handleTypeChange() {
        const selectedOption = typeSelect.options[typeSelect.selectedIndex];
        const typeCode = selectedOption.getAttribute('data-code');
        
        // Tüm özel bölümleri gizle
        const allSections = [
            'section_article',
            'section_conference',
            'section_book',
            'section_book_chapter',
            'section_project'
        ];
        
        allSections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = 'none';
                
                // Bölümdeki tüm required alanları optional yap
                const requiredFields = section.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    field.removeAttribute('required');
                    field.setAttribute('data-was-required', 'true');
                });
            }
        });
        
        // Seçili türe göre ilgili bölümü göster
        let sectionToShow = '';
        
        switch(typeCode) {
            case 'article':
                sectionToShow = 'section_article';
                break;
            case 'conference':
                sectionToShow = 'section_conference';
                break;
            case 'book':
                sectionToShow = 'section_book';
                break;
            case 'book_chapter':
                sectionToShow = 'section_book_chapter';
                break;
            case 'project':
                sectionToShow = 'section_project';
                break;
            case 'patent':
                // Patent için özel alan yok, sadece temel bilgiler
                break;
        }
        
        if (sectionToShow) {
            const section = document.getElementById(sectionToShow);
            if (section) {
                section.style.display = 'block';
                
                // Animasyon ekle
                section.style.animation = 'fadeIn 0.3s ease-in';
                
                // Bu bölümdeki required alanları tekrar required yap
                const fieldsToRequire = section.querySelectorAll('[data-was-required="true"]');
                fieldsToRequire.forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Bölümün ilk alanına scroll yap
                setTimeout(() => {
                    section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }
    }
    
    // Yazar isimlerini otomatik formatla (virgülle ayır)
    const authorsField = document.getElementById('authors');
    if (authorsField) {
        authorsField.addEventListener('blur', function() {
            let value = this.value.trim();
            
            // Noktalı virgül varsa virgüle çevir
            value = value.replace(/;/g, ',');
            
            // Çoklu boşlukları temizle
            value = value.replace(/\s+/g, ' ');
            
            // Virgülden sonra boşluk ekle
            value = value.replace(/,\s*/g, ', ');
            
            this.value = value;
        });
    }
    
    // DOI formatı kontrolü
    const doiField = document.getElementById('doi');
    if (doiField) {
        doiField.addEventListener('blur', function() {
            let value = this.value.trim();
            
            // "doi:" prefix'i varsa kaldır
            value = value.replace(/^doi:\s*/i, '');
            
            // URL formatındaysa sadece DOI'yi al
            const doiMatch = value.match(/10\.\d{4,}\/[^\s]+/);
            if (doiMatch) {
                value = doiMatch[0];
            }
            
            this.value = value;
        });
    }
    
    // Proje tarihleri kontrolü
    const projectStartDate = document.getElementById('project_start_date');
    const projectEndDate = document.getElementById('project_end_date');
    
    if (projectStartDate && projectEndDate) {
        projectEndDate.addEventListener('change', function() {
            const startDate = new Date(projectStartDate.value);
            const endDate = new Date(this.value);
            
            if (startDate && endDate && endDate < startDate) {
                alert('Bitiş tarihi, başlangıç tarihinden önce olamaz!');
                this.value = '';
            }
        });
    }
    
    // Konferans tarihleri kontrolü
    const confStartDate = document.getElementById('conference_start_date');
    const confEndDate = document.getElementById('conference_end_date');
    
    if (confStartDate && confEndDate) {
        confEndDate.addEventListener('change', function() {
            const startDate = new Date(confStartDate.value);
            const endDate = new Date(this.value);
            
            if (startDate && endDate && endDate < startDate) {
                alert('Bitiş tarihi, başlangıç tarihinden önce olamaz!');
                this.value = '';
            }
        });
    }
    
    // Form submit öncesi son kontroller
    const form = document.getElementById('publicationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const typeSelect = document.getElementById('type_id');
            
            if (!typeSelect.value) {
                e.preventDefault();
                alert('Lütfen yayın türü seçiniz!');
                typeSelect.focus();
                return false;
            }
            
            // Loading göster
            if (typeof showLoading === 'function') {
                showLoading('Yayın kaydediliyor...');
            }
        });
    }
    
    // Anahtar kelimeler için tag input özelliği (isteğe bağlı)
    const keywordsField = document.getElementById('keywords');
    if (keywordsField) {
        keywordsField.addEventListener('blur', function() {
            let value = this.value.trim();
            
            // Noktalı virgül varsa virgüle çevir
            value = value.replace(/;/g, ',');
            
            // Virgülden sonra boşluk ekle
            value = value.replace(/,\s*/g, ', ');
            
            // Duplicate'leri temizle
            const keywords = value.split(',').map(k => k.trim()).filter(k => k);
            const uniqueKeywords = [...new Set(keywords)];
            
            this.value = uniqueKeywords.join(', ');
        });
    }
});

// Animasyon CSS'i ekle
if (!document.getElementById('form-animations')) {
    const style = document.createElement('style');
    style.id = 'form-animations';
    style.textContent = `
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
}