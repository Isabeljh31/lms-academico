const sinTildes = (texto) => texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

/**
 * Tablero de Grupos: arrastrar y soltar estudiantes entre «Sin grupo» y los
 * grupos, selección múltiple con casillas o clic, movimiento con
 * teclado (Espacio selecciona, Enter levanta, flechas eligen el destino,
 * Enter suelta, Escape cancela) y menú «Mover a…». Cada zona es un
 * [data-zona] con data-grupo (vacío = Sin grupo), data-cuenta y data-maximo.
 * Livewire guarda en mover(); el DOM que mueve SortableJS se devuelve a su
 * lugar antes de llamar al servidor para que el re-render sea el que manda.
 */
export const tableroGrupos = () => ({
    seleccionados: [],
    alumnosSinGrupo: [],
    cantidadGrupos: 0,
    ultimoClic: null,
    arrastrando: false,
    destino: null,
    destinoBloqueado: false,
    teclado: null,
    menuPara: null,
    busqueda: '',
    anuncio: '',

    montar(zona) {
        if (zona.dataset.zona === 'sin-grupo') {
            this.sincronizarSinGrupo();
        }
        window.cargarSortable().then(() => {
            if (zona._sortable) {
                return;
            }
            zona._sortable = window.Sortable.create(zona, {
                group: 'estudiantes',
                sort: false,
                animation: 150,
                delay: 250,
                delayOnTouchOnly: true,
                draggable: '[data-estudiante]',
                filter: '[data-no-arrastrar]',
                preventOnFilter: false,
                ghostClass: 'grupos-fantasma',
                chosenClass: 'grupos-elegido',
                emptyInsertThreshold: 40,
                onStart: (evento) => {
                    const id = evento.item.dataset.estudiante;
                    if (!this.seleccionados.includes(id)) {
                        this.seleccionados = [id];
                    }
                    this.arrastrando = true;
                    this.anuncio = this.seleccionados.length > 1 ? `Moviendo ${this.seleccionados.length} estudiantes.` : '';
                },
                onMove: (evento) => {
                    this.destino = evento.to.dataset.zona;
                    this.destinoBloqueado = evento.to !== evento.from && !this.cabe(evento.to);

                    return !this.destinoBloqueado;
                },
                onEnd: (evento) => {
                    const { item, from, to, oldIndex } = evento;
                    if (from !== to) {
                        from.insertBefore(item, from.children[oldIndex] ?? null);
                        this.soltarEn(to);
                    }
                    this.arrastrando = false;
                    this.destino = null;
                    this.destinoBloqueado = false;
                },
            });
        });
    },

    zonas() {
        return [...this.$root.querySelectorAll('[data-zona]')];
    },

    sincronizarSinGrupo() {
        const zonas = this.zonas();
        this.cantidadGrupos = zonas.filter((zona) => zona.dataset.grupo).length;
        const zona = zonas.find((zona) => zona.dataset.zona === 'sin-grupo');
        this.alumnosSinGrupo = [...(zona?.querySelectorAll('[data-estudiante]') ?? [])]
            .map((chip) => ({ id: chip.dataset.estudiante, nombre: chip.dataset.nombre }));
    },

    sinGrupoVisibles() {
        return this.alumnosSinGrupo.filter((alumno) => this.visible(alumno.nombre)).map((alumno) => alumno.id);
    },

    seleccionarVisibles() {
        this.seleccionados = [...new Set([...this.seleccionados, ...this.sinGrupoVisibles()])];
    },

    limpiarSeleccion() {
        this.seleccionados = [];
        this.ultimoClic = null;
        this.teclado = null;
        this.destino = null;
        this.destinoBloqueado = false;
    },

    abrirSeleccion() {
        if (this.seleccionados.length > 0) {
            this.menuPara = 'seleccion';
        }
    },

    cabe(zona) {
        if (!zona) {
            return false;
        }
        const maximo = parseInt(zona.dataset.maximo, 10);
        if (!maximo) {
            return true;
        }
        const yaDentro = this.seleccionados.filter((id) => zona.querySelector(`[data-estudiante="${id}"]`)).length;

        return parseInt(zona.dataset.cuenta, 10) - yaDentro + this.seleccionados.length <= maximo;
    },

    soltarEn(zona) {
        const ids = this.seleccionados.map(Number);
        this.seleccionados = [];
        this.teclado = null;
        this.$wire.mover(ids, zona.dataset.grupo ? Number(zona.dataset.grupo) : null);
    },

    elegir(evento, id) {
        if (evento.shiftKey && this.ultimoClic) {
            const chips = [...evento.currentTarget.closest('[data-zona]').querySelectorAll('[data-estudiante]')]
                .filter((chip) => chip.offsetParent !== null)
                .map((chip) => chip.dataset.estudiante);
            const [desde, hasta] = [chips.indexOf(this.ultimoClic), chips.indexOf(id)].sort((x, y) => x - y);
            if (desde >= 0) {
                this.seleccionados = [...new Set([...this.seleccionados, ...chips.slice(desde, hasta + 1)])];

                return;
            }
        }
        if (this.seleccionados.includes(id)) {
            this.seleccionados = this.seleccionados.filter((otro) => otro !== id);
        } else {
            this.seleccionados = [...this.seleccionados, id];
        }
        this.ultimoClic = id;
    },

    teclaEnChip(evento, id) {
        if (this.teclado) {
            return;
        }
        if (evento.key === ' ') {
            evento.preventDefault();
            this.elegir({ ctrlKey: true, currentTarget: evento.currentTarget }, id);
        } else if (evento.key === 'Enter') {
            evento.preventDefault();
            evento.stopPropagation();
            if (!this.seleccionados.includes(id)) {
                this.seleccionados = [id];
            }
            const zonas = this.zonas();
            const origen = zonas.indexOf(evento.currentTarget.closest('[data-zona]'));
            this.teclado = { origen, indice: origen, chip: evento.currentTarget };
            this.destino = zonas[origen].dataset.zona;
            const quien = this.seleccionados.length === 1 ? 'Estudiante levantado' : `${this.seleccionados.length} estudiantes levantados`;
            this.anuncio = `${quien}. Usa las flechas para elegir el grupo, Enter para soltar y Escape para cancelar.`;
        }
    },

    teclaGlobal(evento) {
        if (!this.teclado) {
            return;
        }
        const zonas = this.zonas();
        if (['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(evento.key)) {
            evento.preventDefault();
            const paso = ['ArrowRight', 'ArrowDown'].includes(evento.key) ? 1 : -1;
            this.teclado.indice = (this.teclado.indice + paso + zonas.length) % zonas.length;
            const zona = zonas[this.teclado.indice];
            this.destino = zona.dataset.zona;
            this.destinoBloqueado = this.teclado.indice !== this.teclado.origen && !this.cabe(zona);
            this.anuncio = `${zona.dataset.nombre}${this.destinoBloqueado ? ': grupo completo' : ''}.`;
            zona.closest('[data-tarjeta-zona]')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else if (evento.key === 'Enter') {
            evento.preventDefault();
            if (this.teclado.indice === this.teclado.origen || this.destinoBloqueado) {
                return;
            }
            const zona = zonas[this.teclado.indice];
            this.destino = null;
            this.soltarEn(zona);
        } else if (evento.key === 'Escape') {
            evento.preventDefault();
            this.teclado.chip?.focus();
            this.teclado = null;
            this.destino = null;
            this.destinoBloqueado = false;
            this.anuncio = 'Movimiento cancelado.';
        }
    },

    abrirMenu(id) {
        if (!this.seleccionados.includes(id)) {
            this.seleccionados = [id];
        }
        this.menuPara = id;
    },

    moverA(grupoId) {
        const ids = this.seleccionados.map(Number);
        this.seleccionados = [];
        this.menuPara = null;
        this.$wire.mover(ids, grupoId);
    },

    visible(nombre) {
        return this.busqueda.trim() === '' || sinTildes(nombre).includes(sinTildes(this.busqueda.trim()));
    },
});
