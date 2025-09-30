// ============================
// SISTEMA DE ASISTENCIA - JAVASCRIPT COMPLETO RESPONSIVE
// ============================

class SistemaAsistencia {
    constructor() {
        this.currentViewingFile = null;
        this.currentFileName = null;
        this.currentFileId = null;
        this.schoolNames = {
            '26DST0006K': 'Secundaria Técnica #06',
            '26DST0060K': 'Secundaria Técnica #60', 
            '26DST0072K': 'Secundaria Técnica #72'
        };
        this.isMobile = this.detectMobile();
        this.init();
    }

    detectMobile() {
        return window.innerWidth <= 768;
    }

    init() {
        this.setupEventListeners();
        this.setupNavigation();
        this.updateTurnInfo();
        
        // Aplicar ajustes responsivos inmediatamente
        this.applyResponsiveAdjustments();
        
        // Actualizar información del turno cada minuto
        setInterval(() => this.updateTurnInfo(), 60000);
        
        // Verificar parámetros de URL
        this.checkUrlParams();
        
        // Configurar filtros del repositorio
        this.setupRepositoryFilters();
        
        // Mejorar experiencia móvil
        this.enhanceMobileExperience();
        
        // Optimizar tablas para móviles
        this.optimizeTablesForMobile();
        
        // Configurar eventos para formularios CTZ
        this.setupCTZForms();
        
        // Configurar gestión de destinatarios
        this.configurarDestinatarios();
        
        // Marcar body cuando hay sesión
        this.markBodyForSession();
        
        // Escuchar cambios de tamaño
        this.setupResizeListener();
    }

    setupResizeListener() {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.isMobile = this.detectMobile();
                this.applyResponsiveAdjustments();
                this.optimizeTablesForMobile();
            }, 250);
        });
    }

    applyResponsiveAdjustments() {
        if (this.isMobile) {
            this.applyMobileOptimizations();
        } else {
            this.applyDesktopOptimizations();
        }
    }

    applyMobileOptimizations() {
        // Ajustar elementos específicos para móvil
        const cards = document.querySelectorAll('.card, .event-card, .resource-card');
        cards.forEach(card => {
            card.style.margin = '2px 0';
        });

        // Ajustar botones para touch
        const buttons = document.querySelectorAll('.btn, .btn-icon, .view-file-btn');
        buttons.forEach(button => {
            button.style.minHeight = '44px';
            button.style.minWidth = '44px';
        });

        // Optimizar espacios en login
        this.optimizeLoginForMobile();
    }

    applyDesktopOptimizations() {
        // Restaurar estilos para desktop si es necesario
        const cards = document.querySelectorAll('.card, .event-card, .resource-card');
        cards.forEach(card => {
            card.style.margin = '';
        });
    }

    optimizeLoginForMobile() {
        const loginContainer = document.querySelector('.login-container');
        const bannerContainer = document.querySelector('.banner-container');
        
        if (loginContainer) {
            loginContainer.style.minHeight = '40vh';
            loginContainer.style.margin = '0';
            loginContainer.style.padding = '2px 0';
        }
        
        if (bannerContainer) {
            bannerContainer.style.margin = '0 auto';
            bannerContainer.style.padding = '2px 0';
            bannerContainer.style.minHeight = '40vh';
        }
    }

    setupEventListeners() {
        // Evento para archivos de Excel
        const excelFile = document.getElementById('excelFile');
        if (excelFile) {
            excelFile.addEventListener('change', this.handleFileSelect.bind(this));
        }

        // Configurar botón de login si no hay sesión
        if (!window.appConfig.hasSession) {
            this.setupLoginToggle();
        }

        // Mejorar experiencia táctil
        this.enhanceTouchExperience();
    }

    enhanceTouchExperience() {
        // Prevenir zoom en double tap en iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Mejorar feedback táctil
        const touchElements = document.querySelectorAll('.btn, .card, .nav-tabs a');
        touchElements.forEach(element => {
            element.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            
            element.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });
    }

    setupCTZForms() {
        // Configurar formularios CTZ para que refresquen la página después del envío
        const ctzForms = document.querySelectorAll('form[action*="ctz"]');
        ctzForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Agregar un pequeño delay para permitir que el servidor procese
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        });
    }

    setupNavigation() {
        // Configurar pestañas si hay sesión
        if (window.appConfig.hasSession) {
            this.setupTabs();
            this.setupHamburgerMenu();
        }
    }

    setupTabs() {
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(link, tabContents);
            });
        });

        // Manejar URLs con hash
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            const targetTab = document.querySelector(`.tab-link[data-tab="${hash}"]`);
            if (targetTab) {
                this.switchTab(targetTab, tabContents);
            }
        }
    }

    switchTab(clickedTab, tabContents) {
        // Remover clase active de todos los tabs
        document.querySelectorAll('.tab-link').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Ocultar todos los contenidos
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // Activar la pestaña clickeada
        clickedTab.classList.add('active');
        
        // Mostrar el contenido correspondiente
        const tabId = clickedTab.getAttribute('data-tab');
        const targetContent = document.getElementById(tabId);
        
        if (targetContent) {
            targetContent.classList.add('active');
        }
        
        // Cerrar menú hamburguesa en móviles
        this.closeMobileMenu();
        
        // Actualizar URL
        history.pushState(null, null, `#${tabId}`);
        
        // Re-aplicar ajustes responsivos después de cambiar pestaña
        setTimeout(() => {
            this.applyResponsiveAdjustments();
            this.optimizeTablesForMobile();
        }, 100);
    }

    setupHamburgerMenu() {
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const navTabs = document.getElementById('navTabs');
        
        if (hamburgerMenu && navTabs) {
            hamburgerMenu.addEventListener('click', (e) => {
                e.stopPropagation();
                navTabs.classList.toggle('open');
                hamburgerMenu.classList.toggle('active');
            });
            
            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!navTabs.contains(e.target) && !hamburgerMenu.contains(e.target)) {
                    this.closeMobileMenu();
                }
            });

            // Cerrar menú al seleccionar una opción
            navTabs.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    this.closeMobileMenu();
                });
            });
        }
    }

    closeMobileMenu() {
        const navTabs = document.getElementById('navTabs');
        const hamburgerMenu = document.querySelector('.hamburger-menu');
        
        if (navTabs) navTabs.classList.remove('open');
        if (hamburgerMenu) hamburgerMenu.classList.remove('active');
    }

    setupLoginToggle() {
        const loginToggleBtn = document.getElementById('loginToggleBtn');
        if (loginToggleBtn) {
            loginToggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleLogin();
            });
        }
    }

    toggleLogin() {
        const loginContainer = document.getElementById('loginPage');
        const bannerContainer = document.getElementById('bannerContainer');
        
        if (loginContainer && bannerContainer) {
            const isLoginVisible = window.getComputedStyle(loginContainer).display === 'flex';
            
            if (isLoginVisible) {
                loginContainer.style.display = 'none';
                bannerContainer.style.display = 'flex';
            } else {
                loginContainer.style.display = 'flex';
                bannerContainer.style.display = 'none';
            }
        }
    }

    setupRepositoryFilters() {
        const filterButtons = document.querySelectorAll('.repository-filters .btn');
        const resourceCards = document.querySelectorAll('.resource-card');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remover clase active de todos los botones
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Agregar clase active al botón clickeado
                button.classList.add('active');
                
                const category = button.getAttribute('data-category');
                
                // Filtrar tarjetas
                resourceCards.forEach(card => {
                    if (category === 'all' || card.getAttribute('data-category') === category) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }

    enhanceMobileExperience() {
        // Mejorar la experiencia en dispositivos móviles
        if (this.isMobile) {
            this.optimizeForMobile();
        }
    }

    optimizeForMobile() {
        // Optimizaciones específicas para móviles
        console.log('Optimizando para dispositivo móvil...');
        
        // Asegurar que las tablas sean responsivas
        this.makeTablesResponsive();
        
        // Mejorar los botones para touch
        this.enhanceTouchButtons();
        
        // Ajustar espaciado para usuarios normales
        this.adjustSpacingForNormalUsers();
        
        // Optimizar botones CTZ para móvil
        this.optimizeCTZButtonsForMobile();
        
        // Ajustar espaciado del login en móvil
        this.optimizeLoginSpacing();
        
        // Aplicar espaciado mínimo en móvil
        this.applyMobileSpacing();
    }

    applyMobileSpacing() {
        if (!this.isMobile) return;
        
        // Reducir todos los márgenes y paddings al mínimo
        const elementsToAdjust = [
            '.main-content',
            '.hero',
            '.dashboard',
            '.school-view-container',
            '.merge-section',
            '.destinatarios-container',
            '.history-section',
            '.events-section',
            '.repository-section'
        ];
        
        elementsToAdjust.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.style.marginTop = '0';
                element.style.marginBottom = '5px';
                if (selector === '.main-content') {
                    element.style.padding = '2px 0';
                }
            });
        });
    }

    optimizeLoginSpacing() {
        const loginContainer = document.querySelector('.login-container');
        const bannerContainer = document.querySelector('.banner-container');
        
        if (loginContainer && this.isMobile) {
            loginContainer.style.minHeight = '40vh';
            loginContainer.style.margin = '0';
            loginContainer.style.padding = '2px 0';
        }
        
        if (bannerContainer && this.isMobile) {
            bannerContainer.style.margin = '0 auto';
            bannerContainer.style.padding = '2px 0';
            bannerContainer.style.minHeight = '40vh';
        }
    }

    optimizeCTZButtonsForMobile() {
        const ctzUploadSections = document.querySelectorAll('.document-upload-section .upload-status');
        
        ctzUploadSections.forEach(section => {
            if (this.isMobile) {
                section.style.flexDirection = 'column';
                section.style.alignItems = 'stretch';
                section.style.gap = '8px';
                
                const statusIcons = section.querySelector('.status-icons');
                if (statusIcons) {
                    statusIcons.style.width = '100%';
                    statusIcons.style.justifyContent = 'center';
                    statusIcons.style.flexWrap = 'wrap';
                    statusIcons.style.gap = '6px';
                    
                    const buttons = statusIcons.querySelectorAll('.btn');
                    buttons.forEach(btn => {
                        btn.style.flex = '1';
                        btn.style.minWidth = '120px';
                        btn.style.textAlign = 'center';
                        btn.style.margin = '2px';
                    });
                }
            }
        });
    }

    adjustSpacingForNormalUsers() {
        // Si no es admin, reducir espacios en blanco
        if (!window.appConfig.isAdmin && window.appConfig.hasSession) {
            const schoolViewContainers = document.querySelectorAll('.school-view-container');
            const heroSections = document.querySelectorAll('.tab-content .hero');
            
            schoolViewContainers.forEach(container => {
                container.style.marginBottom = '5px';
            });
            
            heroSections.forEach(hero => {
                hero.style.marginBottom = '8px';
                hero.style.padding = '10px 12px';
            });
        }
    }

    makeTablesResponsive() {
        const tables = document.querySelectorAll('.history-table');
        tables.forEach(table => {
            if (this.isMobile) {
                table.classList.add('mobile-optimized');
            }
        });
    }

    enhanceTouchButtons() {
        const buttons = document.querySelectorAll('.btn, .btn-icon, .view-file-btn');
        buttons.forEach(button => {
            button.style.minHeight = '44px';
            button.style.minWidth = '44px';
            button.style.display = 'flex';
            button.style.alignItems = 'center';
            button.style.justifyContent = 'center';
        });
    }

    // Funciones para gestión de destinatarios
    configurarDestinatarios() {
        const checkboxes = document.querySelectorAll('input[name="destinatarios_seleccionados[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.actualizarContadorSeleccion();
            });
        });
        
        // Actualizar contador inicial
        this.actualizarContadorSeleccion();
        
        // Configurar el formulario para evitar envíos vacíos
        const form = document.getElementById('destinatariosForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                const checkboxes = document.querySelectorAll('input[name="destinatarios_seleccionados[]"]:checked');
                if (checkboxes.length === 0) {
                    e.preventDefault();
                    alert('Por favor seleccione al menos un destinatario.');
                    return false;
                }
                return true;
            });
        }
    }

    seleccionarTodos() {
        const checkboxes = document.querySelectorAll('input[name="destinatarios_seleccionados[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        this.actualizarContadorSeleccion();
    }

    deseleccionarTodos() {
        const checkboxes = document.querySelectorAll('input[name="destinatarios_seleccionados[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        this.actualizarContadorSeleccion();
    }

    actualizarContadorSeleccion() {
        const checkboxes = document.querySelectorAll('input[name="destinatarios_seleccionados[]"]:checked');
        const contador = document.getElementById('selectionInfo');
        if (contador) {
            contador.textContent = `${checkboxes.length} seleccionados`;
        }
    }

    eliminarDestinatario(id) {
        if (confirm('¿Está seguro de eliminar este destinatario? Esta acción no se puede deshacer.')) {
            // Crear formulario dinámico para eliminar
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action_destinatarios';
            actionInput.value = 'eliminar';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Funciones de gestión de archivos
    openUploadModal(turno, schoolId = null) {
        let title = "Subir Reporte - ";
        
        if (window.appConfig.isAdmin && schoolId) {
            title += this.schoolNames[schoolId] + " - Turno " + turno;
        } else {
            title += window.appConfig.schoolName + " - Turno " + turno;
        }
        
        document.getElementById('uploadModalTitle').textContent = title;
        document.getElementById('turnoInput').value = turno;
        document.getElementById('schoolIdInput').value = schoolId;
        document.getElementById('uploadModal').style.display = 'flex';
        
        // Enfocar el primer campo en móvil
        if (this.isMobile) {
            setTimeout(() => {
                const fileInput = document.getElementById('excelFile');
                if (fileInput) fileInput.focus();
            }, 300);
        }
    }

    openUploadModalTrimestral(schoolId = null) {
        let title = "Subir Reporte Trimestral - ";
        
        if (window.appConfig.isAdmin && schoolId) {
            title += this.schoolNames[schoolId];
        } else {
            title += window.appConfig.schoolName;
        }
        
        document.getElementById('uploadModalTitle').textContent = title;
        document.getElementById('turnoInput').value = 'trimestral';
        document.getElementById('schoolIdInput').value = schoolId;
        document.getElementById('uploadModal').style.display = 'flex';
    }

    openUploadModalCTZ() {
        document.getElementById('uploadModalCTZ').style.display = 'flex';
    }

    openUploadModalCTZEscuela(schoolId = null) {
        let title = "Subir CTE Contestado - ";
        
        if (window.appConfig.isAdmin && schoolId) {
            title += this.schoolNames[schoolId];
        } else {
            title += window.appConfig.schoolName;
        }
        
        document.getElementById('uploadModalCTZTitle').textContent = title;
        document.getElementById('ctzSchoolIdInput').value = schoolId;
        document.getElementById('uploadModalCTZEscuela').style.display = 'flex';
    }

    handleUploadSubmit(event) {
        event.preventDefault();
        
        const submitBtn = document.getElementById('uploadSubmitBtn');
        const originalText = submitBtn.innerHTML;
        
        // Mostrar estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
        
        // Enviar formulario
        event.target.submit();
    }

    handleFileSelect(event) {
        const uploadButtons = document.querySelectorAll('.upload-btn');
        uploadButtons.forEach(btn => {
            btn.classList.add('hidden');
        });
    }

    closeUploadModal() {
        document.getElementById('uploadModal').style.display = 'none';
        document.getElementById('uploadForm').reset();
        
        const submitBtn = document.getElementById('uploadSubmitBtn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Subir Archivo';
    }

    closeUploadModalCTZ() {
        document.getElementById('uploadModalCTZ').style.display = 'none';
    }

    closeUploadModalCTZEscuela() {
        document.getElementById('uploadModalCTZEscuela').style.display = 'none';
    }

    // Funciones de visualización
    viewFile(filePath, title, fileId = null) {
        this.currentViewingFile = filePath;
        this.currentFileName = filePath.split('/').pop();
        this.currentFileId = fileId;
        
        document.getElementById('viewFileModalTitle').textContent = title;
        
        document.getElementById('fileViewContent').innerHTML = `
            <div class="file-info">
                <p><strong>Nombre del archivo:</strong> ${this.currentFileName}</p>
                <p><strong>Ubicación:</strong> ${filePath}</p>
            </div>
        `;
        
        const deleteBtn = document.getElementById('deleteFileBtn');
        deleteBtn.style.display = window.appConfig.isAdmin && fileId ? 'inline-block' : 'none';
        
        document.getElementById('viewFileModal').style.display = 'flex';
    }

    viewFileTrimestral(filePath, title, fileId = null) {
        this.viewFile(filePath, title, fileId);
    }

    viewFileCTZ(filePath, title, fileId = null) {
        this.viewFile(filePath, title, fileId);
    }

    closeViewFileModal() {
        document.getElementById('viewFileModal').style.display = 'none';
        this.currentViewingFile = null;
        this.currentFileName = null;
        this.currentFileId = null;
    }
    
    // Función de descarga mejorada
    downloadFile(filePath, fileName) {
        // Verificar si el archivo existe antes de descargar
        fetch(filePath)
            .then(response => {
                if (response.ok) {
                    // Usar el endpoint de descarga
                    const link = document.createElement('a');
                    link.href = 'download.php?file=' + encodeURIComponent(filePath);
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    alert('El archivo no se encuentra disponible en el servidor. Por favor contacte al administrador.');
                }
            })
            .catch(error => {
                console.error('Error al verificar archivo:', error);
                alert('Error al descargar el archivo. Por favor contacte al administrador.');
            });
    }

    downloadCurrentFile() {
        if (this.currentViewingFile) {
            this.downloadFile(this.currentViewingFile, this.currentFileName);
        }
    }

    // Funciones de eliminación mejoradas
    deleteCurrentFile() {
        if (this.currentFileId) {
            this.deleteFile(this.currentFileId);
        }
    }

    deleteFile(fileId) {
        if (confirm('¿Está seguro de eliminar este archivo? Esta acción no se puede deshacer.')) {
            window.location.href = `?delete_file=${fileId}`;
        }
    }

    deleteFileTrimestral(fileId) {
        if (confirm('¿Está seguro de eliminar este reporte trimestral? Esta acción no se puede deshacer.')) {
            window.location.href = `?delete_file_trimestral=${fileId}`;
        }
    }

    deleteFileCTZ(fileId) {
        if (confirm('¿Está seguro de eliminar este documento CTZ? Esta acción no se puede deshacer.')) {
            window.location.href = `?delete_file_ctz=${fileId}`;
        }
    }

    deleteFileCTZEscuela(fileId) {
        if (confirm('¿Está seguro de eliminar este documento CTZ? Esta acción no se puede deshacer.')) {
            window.location.href = `?delete_file_ctz_escuela=${fileId}`;
        }
    }

    // Funciones de consolidación
    mergeFiles(turno) {
        if (confirm(`¿Está seguro de que desea consolidar los archivos del turno ${turno}?`)) {
            this.showLoadingState('mergeBtn', 'mergeSpinner');
            window.location.href = `?action=merge&turno=${turno}`;
        }
    }

    mergeFilesTrimestral() {
        if (confirm('¿Está seguro de que desea consolidar los reportes trimestrales?')) {
            this.showLoadingState('mergeBtnTrimestral', 'mergeSpinnerTrimestral');
            window.location.href = `?action=merge_trimestral`;
        }
    }

    mergeFilesCTZ() {
        if (confirm('¿Está seguro de que desea consolidar los documentos CTZ?')) {
            this.showLoadingState('mergeBtnCTZ', 'mergeSpinnerCTZ');
            window.location.href = `?action=merge_ctz`;
        }
    }

    showLoadingState(buttonId, spinnerId) {
        const button = document.getElementById(buttonId);
        const spinner = document.getElementById(spinnerId);
        
        if (button) button.style.display = 'none';
        if (spinner) spinner.style.display = 'block';
    }

    // Funciones de envío
    sendConsolidated(id) {
        if (confirm('¿Está seguro de que desea enviar este concentrado a los destinatarios? El archivo se eliminará después del envío.')) {
            window.location.href = `?action=send_consolidated&id=${id}`;
        }
    }

    sendConsolidatedTrimestral(id) {
        if (confirm('¿Está seguro de que desea enviar este concentrado trimestral a los destinatarios? El archivo se eliminará después del envío.')) {
            window.location.href = `?action=send_consolidated_trimestral&id=${id}`;
        }
    }

    sendConsolidatedCTZ(id) {
        if (confirm('¿Está seguro de que desea enviar este concentrado CTZ a los destinatarios? El archivo se eliminará después del envío.')) {
            window.location.href = `?action=send_consolidated_ctz&id=${id}`;
        }
    }

    // Funciones de destinatarios
    editarDestinatario(id, email) {
        document.getElementById('editDestinatarioId').value = id;
        document.getElementById('editEmail').value = email;
        document.getElementById('editDestinatarioModal').style.display = 'flex';
    }

    closeEditDestinatarioModal() {
        document.getElementById('editDestinatarioModal').style.display = 'none';
    }

    // Funciones de interfaz
    updateTurnInfo() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        
        const currentTurnElement = document.getElementById('currentTurn');
        const turnTimeElement = document.getElementById('turnTime');
        const nextTurnInfoElement = document.getElementById('nextTurnInfo');
        
        if (currentTurnElement && turnTimeElement && nextTurnInfoElement) {
            if (hours < 13 || (hours === 13 && minutes < 40)) {
                currentTurnElement.textContent = 'Matutino';
                turnTimeElement.textContent = '07:30 AM';
                nextTurnInfoElement.textContent = 'Próximo turno: Vespertino a las 13:40';
            } else {
                currentTurnElement.textContent = 'Vespertino';
                turnTimeElement.textContent = '01:40 PM';
                nextTurnInfoElement.textContent = 'Turno vespertino en curso';
            }
        }
    }

    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('updated')) {
            const schoolId = urlParams.get('school');
            const turno = urlParams.get('turno');
            
            if (schoolId && turno) {
                this.highlightUpdatedStatus(schoolId, turno);
            }
        }
    }

    highlightUpdatedStatus(schoolId, turno) {
        const statusElement = document.getElementById(`status-${schoolId}-${turno}`);
        if (statusElement) {
            statusElement.classList.add('status-updated');
            setTimeout(() => {
                statusElement.classList.remove('status-updated');
            }, 2000);
        }
    }

    // Funciones para mejorar la experiencia móvil
    optimizeTablesForMobile() {
        const tables = document.querySelectorAll('.history-table');
        tables.forEach(table => {
            if (this.isMobile) {
                // Agregar atributos data-label para móviles
                const headers = Array.from(table.querySelectorAll('th'));
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, index) => {
                        if (headers[index]) {
                            cell.setAttribute('data-label', headers[index].textContent);
                        }
                    });
                });
            }
        });
    }

    // Nueva función para alinear elementos en móviles
    alignMobileElements() {
        if (this.isMobile) {
            // Alinear elementos CTZ a la izquierda
            const ctzItems = document.querySelectorAll('.ctz-item');
            ctzItems.forEach(item => {
                const ctzInfo = item.querySelector('.ctz-info');
                if (ctzInfo) {
                    ctzInfo.style.textAlign = 'left';
                }
            });

            // Asegurar que los botones estén en línea horizontal cuando sea posible
            const statusIcons = document.querySelectorAll('.status-icons');
            statusIcons.forEach(icons => {
                if (window.innerWidth > 400) {
                    icons.style.flexDirection = 'row';
                    icons.style.justifyContent = 'flex-end';
                } else {
                    icons.style.flexDirection = 'column';
                    icons.style.alignItems = 'stretch';
                }
            });

            // Ajustar espaciado en upload-status
            const uploadStatuses = document.querySelectorAll('.upload-status');
            uploadStatuses.forEach(status => {
                if (window.innerWidth > 400) {
                    status.style.flexDirection = 'row';
                    status.style.justifyContent = 'space-between';
                } else {
                    status.style.flexDirection = 'column';
                    status.style.alignItems = 'stretch';
                }
            });
        }
    }

    // NUEVA FUNCIÓN: Marcar body cuando hay sesión activa
    markBodyForSession() {
        if (window.appConfig.hasSession) {
            document.body.classList.add('logged-in');
        }
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.sistemaAsistencia = new SistemaAsistencia();
    
    // Ejecutar alineación después de un breve delay para asegurar que el DOM esté completamente cargado
    setTimeout(() => {
        window.sistemaAsistencia.alignMobileElements();
        window.sistemaAsistencia.optimizeCTZButtonsForMobile();
        window.sistemaAsistencia.optimizeLoginSpacing();
        window.sistemaAsistencia.applyMobileSpacing();
    }, 100);
});

// Funciones globales para compatibilidad con HTML onclick
function openUploadModal(turno, schoolId = null) {
    window.sistemaAsistencia.openUploadModal(turno, schoolId);
}

function openUploadModalTrimestral(schoolId = null) {
    window.sistemaAsistencia.openUploadModalTrimestral(schoolId);
}

function openUploadModalCTZ() {
    window.sistemaAsistencia.openUploadModalCTZ();
}

function openUploadModalCTZEscuela(schoolId = null) {
    window.sistemaAsistencia.openUploadModalCTZEscuela(schoolId);
}

function closeUploadModal() {
    window.sistemaAsistencia.closeUploadModal();
}

function closeUploadModalCTZ() {
    window.sistemaAsistencia.closeUploadModalCTZ();
}

function closeUploadModalCTZEscuela() {
    window.sistemaAsistencia.closeUploadModalCTZEscuela();
}

function handleUploadSubmit(event) {
    window.sistemaAsistencia.handleUploadSubmit(event);
}

function viewFile(filePath, title, fileId = null) {
    window.sistemaAsistencia.viewFile(filePath, title, fileId);
}

function viewFileTrimestral(filePath, title, fileId = null) {
    window.sistemaAsistencia.viewFileTrimestral(filePath, title, fileId);
}

function viewFileCTZ(filePath, title, fileId = null) {
    window.sistemaAsistencia.viewFileCTZ(filePath, title, fileId);
}

function closeViewFileModal() {
    window.sistemaAsistencia.closeViewFileModal();
}

function downloadFile(filePath, fileName) {
    window.sistemaAsistencia.downloadFile(filePath, fileName);
}

function downloadCurrentFile() {
    window.sistemaAsistencia.downloadCurrentFile();
}

function deleteFile(fileId) {
    window.sistemaAsistencia.deleteFile(fileId);
}

function deleteFileTrimestral(fileId) {
    window.sistemaAsistencia.deleteFileTrimestral(fileId);
}

function deleteFileCTZ(fileId) {
    window.sistemaAsistencia.deleteFileCTZ(fileId);
}

function deleteFileCTZEscuela(fileId) {
    window.sistemaAsistencia.deleteFileCTZEscuela(fileId);
}

function deleteCurrentFile() {
    window.sistemaAsistencia.deleteCurrentFile();
}

function mergeFiles(turno) {
    window.sistemaAsistencia.mergeFiles(turno);
}

function mergeFilesTrimestral() {
    window.sistemaAsistencia.mergeFilesTrimestral();
}

function mergeFilesCTZ() {
    window.sistemaAsistencia.mergeFilesCTZ();
}

function sendConsolidated(id) {
    window.sistemaAsistencia.sendConsolidated(id);
}

function sendConsolidatedTrimestral(id) {
    window.sistemaAsistencia.sendConsolidatedTrimestral(id);
}

function sendConsolidatedCTZ(id) {
    window.sistemaAsistencia.sendConsolidatedCTZ(id);
}

function editarDestinatario(id, email) {
    window.sistemaAsistencia.editarDestinatario(id, email);
}

function closeEditDestinatarioModal() {
    window.sistemaAsistencia.closeEditDestinatarioModal();
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error global:', e.error);
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Promise rechazada:', e.reason);
    e.preventDefault();
});

// Polyfills para compatibilidad
if (window.NodeList && !NodeList.prototype.forEach) {
    NodeList.prototype.forEach = function(callback, thisArg) {
        thisArg = thisArg || window;
        for (var i = 0; i < this.length; i++) {
            callback.call(thisArg, this[i], i, this);
        }
    };
}

// Mejorar la experiencia táctil en dispositivos móviles
document.addEventListener('touchstart', function() {}, {passive: true});

// Optimizar cuando cambie el tamaño de la ventana
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (window.sistemaAsistencia) {
            window.sistemaAsistencia.applyResponsiveAdjustments();
            window.sistemaAsistencia.optimizeTablesForMobile();
            window.sistemaAsistencia.alignMobileElements();
        }
    }, 250);
});

// Ejecutar alineación también cuando cambien las pestañas
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('tab-link')) {
        setTimeout(() => {
            if (window.sistemaAsistencia) {
                window.sistemaAsistencia.alignMobileElements();
                window.sistemaAsistencia.optimizeCTZButtonsForMobile();
                window.sistemaAsistencia.applyMobileSpacing();
            }
        }, 300);
    }
});

// Prevenir zoom en formularios en iOS
document.addEventListener('touchstart', function() {
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
        document.activeElement.style.fontSize = '16px';
    }
});

document.addEventListener('blur', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        e.target.style.fontSize = '';
    }
}, true);