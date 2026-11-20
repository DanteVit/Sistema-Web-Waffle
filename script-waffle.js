const ITEMS_DEFECTO = ['logo-original', 'fondo-default', 'font-comic'];

// ==========================================================================
// INICIALIZACIÓN GLOBAL
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
    let itemsComprados = JSON.parse(localStorage.getItem('itemsComprados'));
    if (!itemsComprados) {
        itemsComprados = [...ITEMS_DEFECTO];
        localStorage.setItem('itemsComprados', JSON.stringify(itemsComprados));
    }

    //Aplica estilos y avatar cacheados 
    aplicarEstilosGuardados();

    //Sincronizar vistas de tienda y aspecto
    sincronizarVistaTienda(itemsComprados);
    sincronizarVistaAspecto(itemsComprados);

    //Carga perfil, rol, avatar, puntos, compras y aspectos desde MySQL
    cargarUsuarioDesdeServidor();

    //Inicializar módulos específicos de cada vista
    inicializarRepositorio();
    inicializarComunidad();
    inicializarAdminBD();
    inicializarAjustes();
    inicializarContadorPuntosRuffle();
});

function escaparHTML(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}

function normalizarTexto(texto) {
    return String(texto ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function obtenerRolActual() {
    return localStorage.getItem('waffleRole') || 'user';
}

function puedeModerarPublicaciones() {
    const rol = obtenerRolActual();
    return rol === 'admin' || rol === 'moderator';
}

// ==========================================================================
// LOGIN Y SESIÓN
// ==========================================================================
async function login() {
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const errorMsg = document.getElementById('error-msg');

    const mostrarError = (mensaje) => {
        if (errorMsg) {
            errorMsg.style.display = 'block';
            errorMsg.style.color = '#fca5a5';
            errorMsg.style.marginTop = '12px';
            errorMsg.style.fontWeight = 'bold';
            errorMsg.textContent = mensaje;
        } else {
            alert(mensaje);
        }
    };

    if (!usernameInput || !emailInput || !passwordInput) return;

    const username = usernameInput.value.trim();
    const email = emailInput.value.trim();
    const password = passwordInput.value;

    if (!username || !email || !password) {
        mostrarError('Por favor completa todos los campos.');
        return;
    }

    try {
        const res = await fetch('login-waffle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            mostrarError('Error de PHP: ' + texto.replace(/<[^>]*>?/gm, '').trim());
            return;
        }

        if (data.ok) {
            localStorage.setItem('waffleToken', data.token);
            window.location.href = 'index.php';
        } else {
            mostrarError(data.error || 'Credenciales incorrectas.');
        }
    } catch (err) {
        mostrarError('No se pudo conectar con login-waffle.php.');
    }
}

function cerrarSesion(event) {
    if (event) event.preventDefault();
    localStorage.removeItem('waffleToken');
    localStorage.removeItem('waffleAvatar');
    localStorage.removeItem('waffleRole');
    localStorage.removeItem('itemsComprados');
    localStorage.removeItem('skinEquipada');
    localStorage.removeItem('fondoEquipado');
    localStorage.removeItem('fuenteEquipada');
    window.location.href = 'login.html';
}

// ==========================================================================
// CARGA DE PERFIL, ROL, AVATAR Y ASPECTOS DESDE MYSQL (obtenerPerfil.php)
// ==========================================================================
async function cargarUsuarioDesdeServidor() {
    const token = localStorage.getItem('waffleToken');
    if (!token) return;

    try {
        const res = await fetch('obtenerPerfil.php', {
            headers: { 'Authorization': token }
        });
        const data = await res.json();

        if (data.ok) {
            const perfil = data.perfil || data;
            const { username, points, bg_theme, font_family, bg_image, avatar } = perfil;
            const role = perfil.role || (username === 'admin' ? 'admin' : 'user');
            const rolAnterior = localStorage.getItem('waffleRole');

            localStorage.setItem('waffleRole', role);

            // Actualizar nombre y etiqueta de rol si corresponde
            const nombreEl = document.getElementById('nombre-header') || document.querySelector('.nombre-usuario');
            if (nombreEl) {
                nombreEl.textContent = role === 'moderator' ? `${username} [MOD]` : username;
            }

            if (role === 'admin') {
                mostrarEnlaceAdminBD();
            }

            // Si estamos en comunidad y el rol cambió tras cargar el perfil, refrescar botones de moderación
            if (rolAnterior !== role && document.getElementById('foro-lista')) {
                cargarPublicacionesDesdeServidor();
            }

            actualizarPuntosEnPantalla(points);

            // Actualizar avatar en el header y en caché
            const avatarUrl = avatar || 'images/guest.png';
            localStorage.setItem('waffleAvatar', avatarUrl);
            document.querySelectorAll('.avatar-img').forEach(img => {
                img.src = avatarUrl;
            });

            // Sincronizar aspectos equipados desde la base de datos
            if (bg_image) localStorage.setItem('skinEquipada', bg_image);
            if (bg_theme && bg_theme !== 'fondo-default') {
                localStorage.setItem('fondoEquipado', bg_theme);
            } else {
                localStorage.removeItem('fondoEquipado');
            }
            if (font_family && font_family !== 'font-comic') {
                localStorage.setItem('fuenteEquipada', font_family);
            } else {
                localStorage.removeItem('fuenteEquipada');
            }

            aplicarEstilosGuardados();

            // Sincronizar ítems comprados desde la base de datos
            const itemsServidor = data.itemsComprados || data.items || [...ITEMS_DEFECTO];
            const itemsUnicos = Array.from(new Set([...ITEMS_DEFECTO, ...itemsServidor]));
            localStorage.setItem('itemsComprados', JSON.stringify(itemsUnicos));
            sincronizarVistaTienda(itemsUnicos);
            sincronizarVistaAspecto(itemsUnicos);
        } else {
            localStorage.removeItem('waffleToken');
            localStorage.removeItem('waffleAvatar');
            localStorage.removeItem('waffleRole');
        }
    } catch (err) {
        console.error('Error al cargar perfil:', err);
    }
}

function actualizarPuntosEnPantalla(puntos) {
    const puntosHeader = document.getElementById('puntos-header');
    const puntosTienda = document.getElementById('puntos-tienda');
    if (puntosHeader) puntosHeader.textContent = puntos;
    if (puntosTienda) puntosTienda.textContent = puntos;
}

// ==========================================================================
// APLICACIÓN DE ESTILOS Y AVATAR EN EL DOM
// ==========================================================================
function aplicarEstilosGuardados() {
    const avatarGuardado = localStorage.getItem('waffleAvatar');
    if (avatarGuardado) {
        document.querySelectorAll('.avatar-img').forEach(img => {
            img.src = avatarGuardado;
        });
    }

    const skinGuardada = localStorage.getItem('skinEquipada');
    const logo = document.getElementById('logo-header') || document.getElementById('global-logo');
    if (skinGuardada && logo) {
        logo.src = skinGuardada;
    }

    const fondoGuardado = localStorage.getItem('fondoEquipado');
    if (fondoGuardado) {
        document.body.style.background = fondoGuardado;
    }

    const fuenteGuardada = localStorage.getItem('fuenteEquipada');
    if (fuenteGuardada) {
        document.body.style.fontFamily = fuenteGuardada;
    }
}

// ==========================================================================
// TIENDA (tienda.html -> comprarItem.php)
// ==========================================================================
async function comprarItem(idItem, botonSeleccionado) {
    const token = localStorage.getItem('waffleToken');
    if (!token) {
        alert('Debes iniciar sesión para comprar artículos en la tienda.');
        window.location.href = 'login.html';
        return;
    }

    botonSeleccionado.disabled = true;
    const textoOriginal = botonSeleccionado.textContent;
    botonSeleccionado.textContent = 'Comprando...';

    try {
        const res = await fetch('comprarItem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ item_id: idItem })
        });
        const data = await res.json();

        if (data.ok) {
            let itemsComprados = JSON.parse(localStorage.getItem('itemsComprados')) || [...ITEMS_DEFECTO];
            if (!itemsComprados.includes(idItem)) {
                itemsComprados.push(idItem);
                localStorage.setItem('itemsComprados', JSON.stringify(itemsComprados));
            }

            actualizarPuntosEnPantalla(data.nuevosPuntos);
            botonSeleccionado.classList.add('btn-equipado');
            botonSeleccionado.textContent = 'Comprado';
            alert('¡Artículo adquirido y guardado en tu cuenta!');
        } else {
            botonSeleccionado.disabled = false;
            botonSeleccionado.textContent = textoOriginal;
            alert(data.error || 'No se pudo completar la compra.');
        }
    } catch (err) {
        botonSeleccionado.disabled = false;
        botonSeleccionado.textContent = textoOriginal;
        alert('Error de conexión con comprarItem.php');
    }
}

function sincronizarVistaTienda(itemsComprados) {
    itemsComprados.forEach(idItem => {
        const btn = document.querySelector(`[data-item="${idItem}"]`);
        if (btn && btn.closest('main')?.querySelector('h1')?.textContent.includes('Tienda')) {
            btn.classList.add('btn-equipado');
            btn.setAttribute('disabled', 'true');
            btn.textContent = 'Comprado';
        }
    });
}

// ==========================================================================
// ASPECTO Y PERSONALIZACIÓN (aspecto.html -> guardarAspecto.php)
// ==========================================================================
async function guardarAspectoEnServidor(tipo, valor) {
    const token = localStorage.getItem('waffleToken');
    if (!token) return;

    try {
        await fetch('guardarAspecto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ tipo, valor })
        });
    } catch (err) {
        console.error('Error guardando aspecto:', err);
    }
}

function equiparLogo(rutaLogo, boton) {
    localStorage.setItem('skinEquipada', rutaLogo);
    const logo = document.getElementById('logo-header') || document.getElementById('global-logo');
    if (logo) logo.src = rutaLogo;
    actualizarEstadoBotonAspecto(boton);
    guardarAspectoEnServidor('logo', rutaLogo);
}

function equiparFondo(estiloFondo, boton) {
    localStorage.setItem('fondoEquipado', estiloFondo);
    document.body.style.background = estiloFondo;
    actualizarEstadoBotonAspecto(boton);
    guardarAspectoEnServidor('fondo', estiloFondo);
}

function equiparFuente(nombreFuente, boton) {
    localStorage.setItem('fuenteEquipada', nombreFuente);
    document.body.style.fontFamily = nombreFuente;
    actualizarEstadoBotonAspecto(boton);
    guardarAspectoEnServidor('fuente', nombreFuente);
}

function sincronizarVistaAspecto(itemsComprados) {
    const skinActiva = localStorage.getItem('skinEquipada') || 'images/logo-original.png';
    const fondoActivo = localStorage.getItem('fondoEquipado') || '#383838';
    const fuenteActiva = localStorage.getItem('fuenteEquipada') || "'Comic Sans MS', cursive, sans-serif";

    document.querySelectorAll('[data-aspecto-item]').forEach(tarjeta => {
        const idItem = tarjeta.getAttribute('data-aspecto-item');
        const boton = tarjeta.querySelector('.boton-piola');
        if (!boton) return;

        if (!itemsComprados.includes(idItem)) {
            boton.classList.add('btn-placeholder');
            boton.classList.remove('btn-equipar', 'btn-equipado');
            boton.setAttribute('disabled', 'true');
            boton.textContent = 'Bloqueado 🔒';
        } else {
            const tipo = tarjeta.getAttribute('data-tipo');
            const valor = tarjeta.getAttribute('data-valor');

            let esElActivo = false;
            if (tipo === 'logo' && skinActiva === valor) esElActivo = true;
            if (tipo === 'fondo' && fondoActivo === valor) esElActivo = true;
            if (tipo === 'fuente' && fuenteActiva === valor) esElActivo = true;

            if (esElActivo) {
                boton.className = 'boton-piola btn-equipado';
                boton.setAttribute('disabled', 'true');
                boton.textContent = 'Equipado';
            } else {
                boton.className = 'boton-piola btn-equipar';
                boton.removeAttribute('disabled');
                boton.textContent = 'Equipar';
            }
        }
    });
}

function actualizarEstadoBotonAspecto(botonActivo) {
    const contenedorGrid = botonActivo.closest('.tienda-grid');
    if (!contenedorGrid) return;

    contenedorGrid.querySelectorAll('.boton-piola').forEach(btn => {
        if (!btn.textContent.includes('Bloqueado')) {
            btn.className = 'boton-piola btn-equipar';
            btn.removeAttribute('disabled');
            btn.textContent = 'Equipar';
        }
    });

    botonActivo.className = 'boton-piola btn-equipado';
    botonActivo.setAttribute('disabled', 'true');
    botonActivo.textContent = 'Equipado';
}

// ==========================================================================
// REPOSITORIO DE JUEGOS (repositorio.html -> subirJuego.php y obtenerJuegos.php)
// ==========================================================================
let generoSeleccionadoRepo = 'todos';

function formatearNombreGenero(genero) {
    const mapa = {
        accion: 'Acción',
        aventura: 'Aventura',
        estrategia: 'Estrategia',
        plataformas: 'Plataformas',
        clasicos: 'Clásicos'
    };
    const clave = normalizarTexto(genero);
    return mapa[clave] || genero;
}

function filtrarTarjetasRepositorio() {
    const buscador = document.getElementById('buscador-juegos');
    const texto = normalizarTexto(buscador?.value || '');
    const tarjetas = document.querySelectorAll('#grid-juegos .item-card');
    const msgFiltroVacio = document.getElementById('mensaje-filtro-vacio');

    let visibles = 0;
    tarjetas.forEach(card => {
        const titulo = normalizarTexto(card.querySelector('.feature-subtitle')?.textContent || '');
        const generoCard = normalizarTexto(card.dataset.genero || card.querySelector('.item-precio')?.textContent || '');
        const coincideTexto = titulo.includes(texto);
        const coincideGenero = generoSeleccionadoRepo === 'todos' || generoCard === generoSeleccionadoRepo;

        if (coincideTexto && coincideGenero) {
            card.style.display = '';
            visibles++;
        } else {
            card.style.display = 'none';
        }
    });

    if (msgFiltroVacio) {
        msgFiltroVacio.style.display = (tarjetas.length > 0 && visibles === 0) ? 'block' : 'none';
    }
}

function inicializarRepositorio() {
    const gridJuegos = document.getElementById('grid-juegos');
    if (!gridJuegos) return;

    cargarJuegosDesdeServidor();

    const inputSwf = document.getElementById('archivo-swf');
    const formSubida = inputSwf ? inputSwf.closest('form') : null;
    if (formSubida) {
        formSubida.addEventListener('submit', subirJuegoAlServidor);
    }

    const buscador = document.getElementById('buscador-juegos');
    const botonesGenero = document.querySelectorAll('.filtros-genero button');

    if (buscador) {
        buscador.addEventListener('input', filtrarTarjetasRepositorio);
    }

    botonesGenero.forEach(btn => {
        btn.addEventListener('click', () => {
            generoSeleccionadoRepo = normalizarTexto(btn.dataset.categoria || btn.textContent);
            botonesGenero.forEach(b => {
                b.style.backgroundColor = '#2a2a2a';
                b.style.color = '#ffffff';
            });
            btn.style.backgroundColor = '#ffcf2f';
            btn.style.color = '#2a2a2a';
            filtrarTarjetasRepositorio();
        });
    });
}

async function cargarJuegosDesdeServidor() {
    const gridJuegos = document.getElementById('grid-juegos');
    const mensajeVacio = document.getElementById('mensaje-repo-vacio');
    const token = localStorage.getItem('waffleToken');

    if (!gridJuegos || !token) return;

    try {
        const res = await fetch('obtenerJuegos.php', {
            headers: { 'Authorization': token }
        });
        const data = await res.json();

        if (data.ok && Array.isArray(data.juegos) && data.juegos.length > 0) {
            if (mensajeVacio) mensajeVacio.style.display = 'none';

            data.juegos.forEach(juego => {
                const generoNorm = normalizarTexto(juego.genre || 'clasicos');
                const tarjeta = document.createElement('div');
                tarjeta.className = 'item-card';
                tarjeta.dataset.genero = generoNorm;
                tarjeta.innerHTML = `
                    <div class="item-preview">
                        <img src="images/logo-original.png" alt="${escaparHTML(juego.title)}">
                    </div>
                    <div class="item-info">
                        <h3 class="feature-subtitle">${escaparHTML(juego.title)}</h3>
                        <span class="item-precio">${escaparHTML(formatearNombreGenero(juego.genre))}</span>
                        <a href="reproductor.html?game=${encodeURIComponent(juego.file_name)}" class="boton-piola" style="text-decoration: none; display: block;">Jugar ▶</a>
                    </div>
                `;
                gridJuegos.appendChild(tarjeta);
            });

            filtrarTarjetasRepositorio();
        }
    } catch (err) {
        console.error('Error cargando juegos:', err);
    }
}

async function subirJuegoAlServidor(event) {
    event.preventDefault();
    const token = localStorage.getItem('waffleToken');
    if (!token) {
        alert('Debes iniciar sesión para aportar juegos al repositorio.');
        window.location.href = 'login.html';
        return;
    }

    const form = event.target;
    const formData = new FormData(form);
    const btnSubmit = form.querySelector('button[type="submit"]');
    const textoOriginal = btnSubmit.textContent;

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Subiendo...';

    try {
        const res = await fetch('subirJuego.php', {
            method: 'POST',
            headers: { 'Authorization': token },
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            if (data.nuevosPuntos !== undefined) {
                actualizarPuntosEnPantalla(data.nuevosPuntos);
            }
            alert(`¡"${data.juego.title}" se guardó correctamente en el repositorio y ganaste +25 puntos!`);
            form.reset();
            location.reload();
        } else {
            alert(data.error || 'No se pudo subir el juego.');
        }
    } catch (err) {
        alert('Error de conexión con subirJuego.php');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = textoOriginal;
    }
}

// ==========================================================================
// COMUNIDAD Y PUBLICACIONES (comunidad.html)
// ==========================================================================
function obtenerContenedorPublicaciones() {
    let contenedor = document.getElementById('foro-lista')
        || document.getElementById('lista-publicaciones')
        || document.getElementById('contenedor-publicaciones')
        || document.getElementById('publicaciones-container')
        || document.querySelector('.publicaciones-lista');

    if (!contenedor) {
        const pVacio = Array.from(document.querySelectorAll('p')).find(p =>
            p.textContent.includes('Todavía no hay publicaciones')
        );
        if (pVacio) {
            contenedor = document.createElement('div');
            contenedor.id = 'lista-publicaciones';
            pVacio.replaceWith(contenedor);
        }
    }
    return contenedor;
}

function inicializarComunidad() {
    const contenedor = obtenerContenedorPublicaciones();
    if (!contenedor) return;

    cargarPublicacionesDesdeServidor();

    const formPublicacion = document.getElementById('form-publicacion') || document.querySelector('form');
    if (formPublicacion && !formPublicacion.dataset.comunidadConectada && !formPublicacion.getAttribute('onsubmit')) {
        formPublicacion.dataset.comunidadConectada = 'true';
        formPublicacion.addEventListener('submit', crearPublicacionEnServidor);
    }
}

function formatearTextoRating(promedio, totalVotos, miVoto) {
    const prom = Number(promedio || 0).toFixed(1);
    const votos = Number(totalVotos || 0);
    const sufijo = votos === 1 ? 'voto' : 'votos';
    const prefijoMiVoto = Number(miVoto || 0) > 0 ? `Tu voto: ${miVoto} ★ • ` : '';
    return `${prefijoMiVoto}Promedio: ${prom} (${votos} ${sufijo})`;
}

async function cargarPublicacionesDesdeServidor() {
    const contenedor = obtenerContenedorPublicaciones();
    if (!contenedor) return;

    const token = localStorage.getItem('waffleToken') || '';
    const esModeradorOAdmin = puedeModerarPublicaciones();

    try {
        const res = await fetch('obtenerPublicaciones.php', {
            headers: token ? { 'Authorization': token } : {}
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            const errorLimpio = texto.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
            contenedor.innerHTML = `<p style="color:#ff6b6b; text-align:center;">Error en obtenerPublicaciones.php (HTTP ${res.status}): ${errorLimpio || 'Respuesta vacía'}</p>`;
            return;
        }

        if (!data.ok) {
            contenedor.innerHTML = `<p style="color:#ff6b6b; text-align:center;">${escaparHTML(data.error || 'No se pudieron cargar las publicaciones.')}</p>`;
            return;
        }

        const publicaciones = data.publicaciones || [];

        if (publicaciones.length === 0) {
            contenedor.innerHTML = `<p style="color:#aaa; text-align:center; padding:20px;">Todavía no hay publicaciones en la comunidad. ¡Sé el primero en escribir una arriba!</p>`;
            return;
        }

        contenedor.innerHTML = publicaciones.map(pub => {
            const idPub = Number(pub.id_publication);
            const avatar = escaparHTML(pub.avatar || 'images/guest.png');
            const autor = escaparHTML(pub.author || 'Usuario');
            const titulo = escaparHTML(pub.title || '');
            const contenido = escaparHTML(pub.content || '').replace(/\n/g, '<br>');
            const fecha = pub.created_at ? `<span style="font-size:0.8rem; color:#aaa;"> • ${escaparHTML(pub.created_at)}</span>` : '';
            const miVoto = Number(pub.mi_calificacion || 0);
            const estrellasActivas = miVoto > 0 ? miVoto : Math.round(Number(pub.promedio || 0));

            let estrellasHTML = '';
            for (let i = 1; i <= 5; i++) {
                const activa = i <= estrellasActivas;
                estrellasHTML += `<span class="btn-estrella ${activa ? 'activa' : ''}" onclick="calificarPublicacion(${idPub}, ${i}, this)" style="cursor:pointer; font-size:1.35rem; color:${activa ? '#ffcf2f' : '#666'}; user-select:none; padding-right:2px;" title="Calificar con ${i} estrella(s)">★</span>`;
            }

            const botonEliminarHTML = esModeradorOAdmin
                ? `<button type="button" class="boton-piola btn-danger" onclick="eliminarPublicacion(${idPub})" style="margin-left:auto; width:auto; padding:6px 12px; font-size:0.82rem;">🗑️ Eliminar</button>`
                : '';

            return `
                <article class="publicacion-card" data-pub-id="${idPub}" style="background:#262626; border:1px solid #3c3c3c; border-radius:12px; padding:16px; margin-bottom:16px;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <img src="${avatar}" alt="${autor}" onerror="this.src='images/guest.png'" style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid #ffcf2f;">
                        <div>
                            <strong style="color:#ffcf2f;">${autor}</strong>${fecha}
                        </div>
                        ${botonEliminarHTML}
                    </div>
                    <h3 style="margin:0 0 8px 0; color:#fff;">${titulo}</h3>
                    <p style="margin:0 0 12px 0; color:#ddd; line-height:1.45;">${contenido}</p>
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; border-top:1px solid #383838; padding-top:8px;">
                        <div class="estrellas-contenedor">${estrellasHTML}</div>
                        <span id="rating-post-${idPub}" class="rating-resumen" style="font-size:0.85rem; color:#bbb;">${formatearTextoRating(pub.promedio, pub.total_votos, miVoto)}</span>
                    </div>
                </article>
            `;
        }).join('');
    } catch (err) {
        console.error('Error al cargar publicaciones:', err);
        contenedor.innerHTML = `<p style="color:#ff6b6b; text-align:center;">Error de conexión con obtenerPublicaciones.php</p>`;
    }
}

async function crearPublicacionEnServidor(event) {
    event.preventDefault();
    const token = localStorage.getItem('waffleToken');
    if (!token) {
        alert('Debes iniciar sesión para publicar.');
        window.location.href = 'login.html';
        return;
    }

    const form = event.target;
    const inputTitulo = form.querySelector('input[type="text"], input:not([type])');
    const inputContenido = form.querySelector('textarea');

    const title = (inputTitulo?.value || '').trim();
    const content = (inputContenido?.value || '').trim();

    if (!title || !content) {
        alert('Completá el título y el contenido de la publicación.');
        return;
    }

    const btnSubmit = form.querySelector('button[type="submit"]');
    const textoOriginal = btnSubmit ? btnSubmit.textContent : '';
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Publicando...';
    }

    try {
        const res = await fetch('crearPublicacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ title, content })
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            alert(`Error en crearPublicacion.php (HTTP ${res.status}): ${texto.replace(/<[^>]*>?/gm, '').trim()}`);
            return;
        }

        if (data.ok) {
            form.reset();
            await cargarPublicacionesDesdeServidor();
        } else {
            alert(data.error || 'No se pudo crear la publicación.');
        }
    } catch (err) {
        alert('Error de conexión con crearPublicacion.php: ' + err.message);
    } finally {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = textoOriginal;
        }
    }
}

async function eliminarPublicacion(idPublicacion) {
    const token = localStorage.getItem('waffleToken');
    if (!token || !puedeModerarPublicaciones()) {
        alert('No tenés permisos para eliminar publicaciones.');
        return;
    }

    if (!confirm('¿Seguro que querés eliminar esta publicación de la comunidad?')) return;

    try {
        const res = await fetch('eliminarPublicacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ id_publication: idPublicacion })
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            alert(`Error en eliminarPublicacion.php (HTTP ${res.status}): ${texto.replace(/<[^>]*>?/gm, '').trim()}`);
            return;
        }

        if (data.ok) {
            await cargarPublicacionesDesdeServidor();
        } else {
            alert(data.error || 'No se pudo eliminar la publicación.');
        }
    } catch (err) {
        alert('Error de conexión con eliminarPublicacion.php: ' + err.message);
    }
}

async function calificarPublicacion(idPublicacion, cantidad, botonClickeado) {
    const token = localStorage.getItem('waffleToken');
    if (!token) {
        alert('Debes iniciar sesión para calificar publicaciones.');
        window.location.href = 'login.html';
        return;
    }

    try {
        const res = await fetch('calificarPublicacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ id_publication: idPublicacion, stars: cantidad })
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            alert(`Error en calificarPublicacion.php (HTTP ${res.status}): ${texto.replace(/<[^>]*>?/gm, '').trim()}`);
            return;
        }

        if (data.ok) {
            const tarjeta = (botonClickeado && botonClickeado.closest('article'))
                || document.querySelector(`article[data-pub-id="${idPublicacion}"]`);

            if (tarjeta) {
                const estrellas = tarjeta.querySelectorAll('.btn-estrella');
                estrellas.forEach((estrella, index) => {
                    const activa = index < Number(data.mis_estrellas);
                    estrella.classList.toggle('activa', activa);
                    estrella.style.color = activa ? '#ffcf2f' : '#666';
                });
            }

            const textoPromedio = document.getElementById(`rating-post-${idPublicacion}`)
                || tarjeta?.querySelector('.rating-resumen');
            if (textoPromedio) {
                textoPromedio.textContent = formatearTextoRating(data.promedio, data.total_votos, data.mis_estrellas);
            }
        } else {
            alert(data.error || 'No se pudo guardar la calificación.');
        }
    } catch (err) {
        alert('Error de conexión con calificarPublicacion.php: ' + err.message);
    }
}

const calificarPost = calificarPublicacion;

// ==========================================================================
// PANEL DE BASE DE DATOS PARA ADMIN (base-datos.html -> adminDb.php)
// ==========================================================================
let tablaActualAdmin = 'users';
let estructuraActualAdmin = null;

function mostrarEnlaceAdminBD() {
    const navCenter = document.querySelector('.nav-center');
    if (!navCenter || document.getElementById('nav-admin-db')) return;

    const link = document.createElement('a');
    link.href = 'base-datos.html';
    link.className = 'btn-nav';
    link.id = 'nav-admin-db';
    link.textContent = 'Base de Datos';
    navCenter.appendChild(link);
}

async function inicializarAdminBD() {
    const contenedorTablas = document.getElementById('lista-tablas-admin');
    if (!contenedorTablas) return;

    const token = localStorage.getItem('waffleToken');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    await cargarListaTablasAdmin();
    await cargarTablaAdmin(tablaActualAdmin);

    const filtroInput = document.getElementById('filtro-filas-bd');
    if (filtroInput) {
        filtroInput.addEventListener('input', () => {
            const q = filtroInput.value.toLowerCase().trim();
            document.querySelectorAll('#tbody-editor-bd tr').forEach(tr => {
                const textos = Array.from(tr.querySelectorAll('input, td')).map(el => (el.value || el.textContent || '').toLowerCase()).join(' ');
                tr.style.display = textos.includes(q) ? '' : 'none';
            });
        });
    }

    const formNuevo = document.getElementById('form-nuevo-registro');
    if (formNuevo) {
        formNuevo.addEventListener('submit', insertarFilaAdmin);
    }
}

async function cargarListaTablasAdmin() {
    const contenedorTablas = document.getElementById('lista-tablas-admin');
    const token = localStorage.getItem('waffleToken');

    try {
        const res = await fetch('adminDb.php?action=listar_tablas', {
            headers: { 'Authorization': token }
        });
        const data = await res.json();

        if (!data.ok) {
            alert(data.error || 'Acceso denegado.');
            window.location.href = 'index.php';
            return;
        }

        contenedorTablas.innerHTML = '';
        data.tablas.forEach(t => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-nav';
            btn.style.border = 'none';
            btn.style.cursor = 'pointer';
            btn.style.backgroundColor = (t.nombre === tablaActualAdmin) ? '#ffcf2f' : '#2a2a2a';
            btn.style.color = (t.nombre === tablaActualAdmin) ? '#2a2a2a' : '#ffffff';
            btn.textContent = `${t.nombre} (${t.total})`;
            btn.onclick = () => {
                tablaActualAdmin = t.nombre;
                toggleFormNuevoRegistro(false);
                cargarListaTablasAdmin();
                cargarTablaAdmin(t.nombre);
            };
            contenedorTablas.appendChild(btn);
        });
    } catch (err) {
        console.error('Error listando tablas:', err);
    }
}

async function cargarTablaAdmin(nombreTabla) {
    const thead = document.getElementById('thead-editor-bd');
    const tbody = document.getElementById('tbody-editor-bd');
    const titulo = document.getElementById('titulo-tabla-activa');
    const token = localStorage.getItem('waffleToken');

    if (titulo) titulo.textContent = `Tabla: ${nombreTabla}`;
    tbody.innerHTML = '<tr><td style="text-align:center; padding:15px;">Cargando registros...</td></tr>';

    try {
        const res = await fetch(`adminDb.php?action=obtener_tabla&tabla=${encodeURIComponent(nombreTabla)}`, {
            headers: { 'Authorization': token }
        });

        const texto = await res.text();
        let data;

        try {
            data = JSON.parse(texto);
        } catch (e) {
            tbody.innerHTML = `<tr><td style="color:#fca5a5; padding:15px;">Error HTTP ${res.status}: ${escaparHTML(texto.replace(/<[^>]*>?/gm, '').trim())}</td></tr>`;
            return;
        }

        if (!data.ok) {
            tbody.innerHTML = `<tr><td style="color:#fca5a5; padding:15px;">${escaparHTML(data.error)}</td></tr>`;
            return;
        }

        estructuraActualAdmin = data;
        construirFormularioNuevoRegistro(data);

        thead.innerHTML = `
            <tr>
                ${data.columnas.map(c => `<th>${c.Field}${c.Key === 'PRI' ? ' 🔑' : ''}</th>`).join('')}
                <th>Acciones</th>
            </tr>
        `;

        if (data.filas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${data.columnas.length + 1}" style="text-align:center; padding:15px;">Esta tabla no tiene registros todavía.</td></tr>`;
            return;
        }

        tbody.innerHTML = '';
        data.filas.forEach(fila => {
            const pkVal = fila[data.pk];
            const tr = document.createElement('tr');

            data.columnas.forEach(col => {
                const td = document.createElement('td');
                const valorActual = fila[col.Field] ?? '';

                if (col.Field === data.pk) {
                    td.textContent = valorActual;
                    td.style.fontWeight = 'bold';
                } else {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = valorActual;
                    input.dataset.columna = col.Field;
                    input.style.width = '100%';
                    input.style.minWidth = '110px';
                    input.style.padding = '6px 8px';
                    input.style.borderRadius = '6px';
                    input.style.border = '1px solid #555';
                    input.style.background = '#1f1f1f';
                    input.style.color = '#fff';
                    input.addEventListener('input', () => {
                        input.style.borderColor = '#ffcf2f';
                    });
                    td.appendChild(input);
                }
                tr.appendChild(td);
            });

            const tdAcciones = document.createElement('td');
            tdAcciones.style.whiteSpace = 'nowrap';
            tdAcciones.style.display = 'flex';
            tdAcciones.style.gap = '6px';

            const btnGuardar = document.createElement('button');
            btnGuardar.type = 'button';
            btnGuardar.className = 'boton-piola';
            btnGuardar.style.padding = '6px 10px';
            btnGuardar.style.fontSize = '0.85rem';
            btnGuardar.textContent = 'Guardar';
            btnGuardar.onclick = () => guardarFilaAdmin(data.tabla, data.pk, pkVal, tr, btnGuardar);

            const btnEliminar = document.createElement('button');
            btnEliminar.type = 'button';
            btnEliminar.className = 'boton-piola btn-danger';
            btnEliminar.style.padding = '6px 10px';
            btnEliminar.style.fontSize = '0.85rem';
            btnEliminar.textContent = 'Eliminar';
            btnEliminar.onclick = () => eliminarFilaAdmin(data.tabla, data.pk, pkVal);

            tdAcciones.appendChild(btnGuardar);
            tdAcciones.appendChild(btnEliminar);
            tr.appendChild(tdAcciones);

            tbody.appendChild(tr);
        });
    } catch (err) {
        tbody.innerHTML = `<tr><td style="color:#fca5a5; padding:15px;">Error de red: ${escaparHTML(err.message)}</td></tr>`;
    }
}

async function guardarFilaAdmin(tabla, pkCol, pkVal, tr, btn) {
    const token = localStorage.getItem('waffleToken');
    const valores = {};
    tr.querySelectorAll('input[data-columna]').forEach(inp => {
        valores[inp.dataset.columna] = inp.value;
    });

    const textoOriginal = btn.textContent;
    btn.disabled = true;
    btn.textContent = '...';

    try {
        const res = await fetch('adminDb.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': token },
            body: JSON.stringify({ action: 'actualizar_fila', tabla, pk_col: pkCol, pk_val: pkVal, valores })
        });
        const data = await res.json();

        if (data.ok) {
            tr.querySelectorAll('input').forEach(inp => inp.style.borderColor = '#4ade80');
            if (tabla === 'users' || tabla === 'configurations') {
                cargarUsuarioDesdeServidor();
            }
        } else {
            alert(data.error || 'No se pudo guardar el cambio.');
        }
    } catch (err) {
        alert('Error de conexión con adminDb.php');
    } finally {
        btn.disabled = false;
        btn.textContent = textoOriginal;
    }
}

async function eliminarFilaAdmin(tabla, pkCol, pkVal) {
    if (!confirm(`¿Seguro que quieres eliminar el registro #${pkVal} de la tabla "${tabla}"?`)) return;

    const token = localStorage.getItem('waffleToken');
    try {
        const res = await fetch('adminDb.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': token },
            body: JSON.stringify({ action: 'eliminar_fila', tabla, pk_col: pkCol, pk_val: pkVal })
        });
        const data = await res.json();

        if (data.ok) {
            await cargarListaTablasAdmin();
            await cargarTablaAdmin(tabla);
        } else {
            alert(data.error || 'No se pudo eliminar el registro.');
        }
    } catch (err) {
        alert('Error de conexión con adminDb.php');
    }
}

function toggleFormNuevoRegistro(mostrar) {
    const sec = document.getElementById('seccion-nuevo-registro');
    if (sec) sec.style.display = mostrar ? 'block' : 'none';
}

function construirFormularioNuevoRegistro(data) {
    const contenedor = document.getElementById('campos-nuevo-registro');
    const titulo = document.getElementById('titulo-nuevo-registro');
    if (!contenedor) return;

    if (titulo) titulo.textContent = `➕ Nuevo Registro en "${data.tabla}"`;
    contenedor.innerHTML = '';

    data.columnas.forEach(col => {
        if (col.Extra && col.Extra.includes('auto_increment')) return;

        const div = document.createElement('div');
        div.className = 'form-group';
        div.style.marginBottom = '0';

        const label = document.createElement('label');
        label.textContent = `${col.Field} (${col.Type})`;

        const input = document.createElement('input');
        input.type = 'text';
        input.name = col.Field;
        input.placeholder = col.Default !== null ? `Por defecto: ${col.Default}` : col.Field;

        div.appendChild(label);
        div.appendChild(input);
        contenedor.appendChild(div);
    });
}

async function insertarFilaAdmin(event) {
    event.preventDefault();
    const token = localStorage.getItem('waffleToken');
    const formData = new FormData(event.target);
    const valores = {};
    formData.forEach((val, key) => {
        if (val !== '') valores[key] = val;
    });

    try {
        const res = await fetch('adminDb.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': token },
            body: JSON.stringify({ action: 'insertar_fila', tabla: tablaActualAdmin, valores })
        });
        const data = await res.json();

        if (data.ok) {
            event.target.reset();
            toggleFormNuevoRegistro(false);
            await cargarListaTablasAdmin();
            await cargarTablaAdmin(tablaActualAdmin);
        } else {
            alert(data.error || 'No se pudo insertar el registro.');
        }
    } catch (err) {
        alert('Error de conexión con adminDb.php');
    }
}

// ==========================================================================
// AJUSTES DEL SISTEMA (ajustes.html)
// ==========================================================================
function inicializarAjustes() {
    const formPerfil = document.getElementById('panel-editar-perfil');
    if (!formPerfil) return;

    const token = localStorage.getItem('waffleToken');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    configurarVistaPreviaAvatar();
    cargarDatosPerfilEnAjustes();
    cargarPreferenciasNotificaciones();

    formPerfil.addEventListener('submit', guardarPerfilDesdeAjustes);

    const formPass = document.getElementById('panel-cambiar-password');
    if (formPass) {
        formPass.addEventListener('submit', cambiarPasswordDesdeAjustes);
    }

    const formNotif = document.getElementById('panel-notificaciones');
    if (formNotif) {
        formNotif.addEventListener('submit', guardarPreferenciasNotificaciones);
    }
}

function togglePanelAjustes(idPanel) {
    const panel = document.getElementById(idPanel);
    if (!panel) return;
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}

function configurarVistaPreviaAvatar() {
    const selectAvatar = document.getElementById('ajustes-avatar-preset');
    const fileAvatar   = document.getElementById('ajustes-avatar-file');
    const previewImg   = document.getElementById('preview-avatar-ajustes');

    if (selectAvatar && previewImg) {
        selectAvatar.addEventListener('change', () => {
            if (fileAvatar) fileAvatar.value = '';
            previewImg.src = selectAvatar.value;
        });
    }

    if (fileAvatar && previewImg) {
        fileAvatar.addEventListener('change', () => {
            if (fileAvatar.files && fileAvatar.files[0]) {
                previewImg.src = URL.createObjectURL(fileAvatar.files[0]);
            }
        });
    }
}

async function cargarDatosPerfilEnAjustes() {
    const token = localStorage.getItem('waffleToken');
    try {
        const res = await fetch('actualizarPerfil.php', {
            headers: { 'Authorization': token }
        });
        const data = await res.json();

        if (data.ok) {
            document.getElementById('ajustes-username').value = data.username || '';
            document.getElementById('ajustes-email').value    = data.email || '';

            const avatarActual = data.avatar || 'images/guest.png';
            document.getElementById('preview-avatar-ajustes').src = avatarActual;
            localStorage.setItem('waffleAvatar', avatarActual);

            const selectAvatar = document.getElementById('ajustes-avatar-preset');
            const existeOpcion = Array.from(selectAvatar.options).some(opt => opt.value === avatarActual);
            if (!existeOpcion) {
                const optCustom = document.createElement('option');
                optCustom.value = avatarActual;
                optCustom.textContent = 'Avatar personalizado actual';
                selectAvatar.appendChild(optCustom);
            }
            selectAvatar.value = avatarActual;
        }
    } catch (err) {
        console.error('Error cargando datos de perfil:', err);
    }
}

async function guardarPerfilDesdeAjustes(event) {
    event.preventDefault();
    const token = localStorage.getItem('waffleToken');

    const formData = new FormData();
    formData.append('username', document.getElementById('ajustes-username').value.trim());
    formData.append('email', document.getElementById('ajustes-email').value.trim());
    formData.append('avatar_preset', document.getElementById('ajustes-avatar-preset').value);

    const fileInput = document.getElementById('ajustes-avatar-file');
    if (fileInput.files && fileInput.files[0]) {
        formData.append('avatar_file', fileInput.files[0]);
    }

    try {
        const res = await fetch('actualizarPerfil.php', {
            method: 'POST',
            headers: { 'Authorization': token },
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            localStorage.setItem('waffleAvatar', data.avatar);
            document.getElementById('preview-avatar-ajustes').src = data.avatar;
            document.querySelectorAll('.avatar-img').forEach(img => {
                img.src = data.avatar;
            });

            const selectAvatar = document.getElementById('ajustes-avatar-preset');
            const existeOpcion = Array.from(selectAvatar.options).some(opt => opt.value === data.avatar);
            if (!existeOpcion) {
                const optCustom = document.createElement('option');
                optCustom.value = data.avatar;
                optCustom.textContent = 'Avatar personalizado actual';
                selectAvatar.appendChild(optCustom);
            }
            selectAvatar.value = data.avatar;

            fileInput.value = '';
            alert('Perfil y avatar actualizados correctamente.');
            togglePanelAjustes('panel-editar-perfil');
        } else {
            alert(data.error || 'No se pudo actualizar el perfil.');
        }
    } catch (err) {
        alert('Error de conexión con actualizarPerfil.php');
    }
}

async function cambiarPasswordDesdeAjustes(event) {
    event.preventDefault();
    const token = localStorage.getItem('waffleToken');

    const actual    = document.getElementById('pass-actual').value;
    const nueva     = document.getElementById('pass-nueva').value;
    const confirmar = document.getElementById('pass-confirmar').value;

    try {
        const res = await fetch('cambiarPassword.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token
            },
            body: JSON.stringify({ actual, nueva, confirmar })
        });
        const data = await res.json();

        if (data.ok) {
            alert('Contraseña actualizada con éxito.');
            event.target.reset();
            togglePanelAjustes('panel-cambiar-password');
        } else {
            alert(data.error || 'No se pudo cambiar la contraseña.');
        }
    } catch (err) {
        alert('Error de conexión con cambiarPassword.php');
    }
}

function cargarPreferenciasNotificaciones() {
    const guardadas = JSON.parse(localStorage.getItem('waffleNotificaciones') || '{}');
    document.getElementById('notif-tienda').checked    = guardadas.tienda !== false;
    document.getElementById('notif-comunidad').checked = guardadas.comunidad !== false;
    document.getElementById('notif-sistema').checked   = guardadas.sistema !== false;
}

function guardarPreferenciasNotificaciones(event) {
    event.preventDefault();
    const prefs = {
        tienda: document.getElementById('notif-tienda').checked,
        comunidad: document.getElementById('notif-comunidad').checked,
        sistema: document.getElementById('notif-sistema').checked
    };
    localStorage.setItem('waffleNotificaciones', JSON.stringify(prefs));
    alert('Preferencias de notificaciones guardadas.');
    togglePanelAjustes('panel-notificaciones');
}

// ==========================================================================
// GANAR +1 PUNTO POR MINUTO JUGANDO EN RUFFLE (reproductor.html)
// ==========================================================================
function inicializarContadorPuntosRuffle() {
    const paginaActual = window.location.pathname.split('/').pop().toLowerCase();
    if (paginaActual !== 'reproductor.html') return;

    const params = new URLSearchParams(window.location.search);
    const archivoJuego = params.get('game');
    const token = localStorage.getItem('waffleToken');

    if (!archivoJuego || !token) return;

    const badgeTiempo = document.createElement('div');
    badgeTiempo.id = 'hud-puntos-ruffle';
    badgeTiempo.style.cssText = `
        position: fixed;
        bottom: 16px;
        right: 16px;
        background: rgba(24, 24, 24, 0.92);
        color: #ffcf2f;
        border: 2px solid #ffcf2f;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.9rem;
        font-weight: bold;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    `;
    badgeTiempo.textContent = '🎮 Esperando que inicie Ruffle...';
    document.body.appendChild(badgeTiempo);

    let segundosActivos = 0;
    let enviandoPunto = false;

    const ruffleEstaEjecutando = () => {
        if (document.hidden) return false;
        const player = document.querySelector('ruffle-player, ruffle-embed, ruffle-object');
        if (!player) return false;
        const tieneDimensiones = player.offsetWidth > 0 && player.offsetHeight > 0;
        const tieneCanvas = player.shadowRoot && player.shadowRoot.querySelector('canvas');
        return Boolean(tieneDimensiones && tieneCanvas);
    };

    setInterval(async () => {
        if (!ruffleEstaEjecutando()) {
            badgeTiempo.textContent = document.hidden
                ? '⏸️ Juego en pausa (pestaña oculta)'
                : '🎮 Esperando juego en Ruffle...';
            return;
        }

        segundosActivos++;
        const restantes = 60 - segundosActivos;
        badgeTiempo.textContent = `🎮 Jugando • Próximo +1 pt en ${restantes}s`;

        if (segundosActivos >= 60 && !enviandoPunto) {
            enviandoPunto = true;
            segundosActivos = 0;

            try {
                const res = await fetch('sumarPuntosJuego.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    body: JSON.stringify({ game: archivoJuego })
                });
                const data = await res.json();

                if (data.ok) {
                    actualizarPuntosEnPantalla(data.nuevosPuntos);
                    badgeTiempo.textContent = `⭐ ¡+1 punto ganado! (Total: ${data.nuevosPuntos} pts)`;
                }
            } catch (err) {
                console.error('Error sumando punto por juego:', err);
            } finally {
                enviandoPunto = false;
            }
        }
    }, 1000);
}