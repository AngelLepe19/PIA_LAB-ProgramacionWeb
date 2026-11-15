let categories = ["Ficción", "Ciencia", "Historia", "Tecnología", "Novela"];
        
        let books = [
            { id: "1", title: "Cien Años de Soledad", author: "Gabriel García Márquez", category: "Ficción", downloads: 142, rating: "4.9", synopsis: "Obra cumbre de la literatura hispanoamericana." },
            { id: "2", title: "Breves Respuestas a las Grandes Preguntas", author: "Stephen Hawking", category: "Ciencia", downloads: 89, rating: "4.7", synopsis: "Reflexiones sobre el universo y el futuro de la humanidad." },
            { id: "3", title: "Sapiens: De animales a dioses", author: "Yuval Noah Harari", category: "Historia", downloads: 210, rating: "4.8", synopsis: "Una breve historia de la humanidad." }
        ];

        let users = [
            { name: "Administrador", email: "admin@correo.com", role: "admin" },
            { name: "Trabajador", email: "worker@correo.com", role: "worker" },
            { name: "Autor", email: "autor@correo.com", role: "author" },
            { name: "Usuario", email: "user@correo.com", role: "user" }
        ];

        let contactMessages = [
            { id: "MSG-101", name: "Juan Pérez", email: "juan@gmail.com", subject: "Consulta de Catálogo", message: "Hola, ¿cuándo estará disponible el nuevo libro?", status: "Pendiente" }
        ];

        let reviews = [
            { id: "r1", bookId: "1", userName: "Usuario", rating: 5, text: "Excelente libro, súper recomendado.", status: "Aprobada" }
        ];

        let orders = [];
        let cart = [];
        let wishlist = [];
        let currentUser = null;
        let currentDetailBookId = null;

        const roleLabels = {
            'user': 'Usuario',
            'author': 'Autor',
            'worker': 'Trabajador',
            'admin': 'Administrador'
        };

       window.onload = function () {
            if (document.getElementById('inicio')) {
                renderCatalog();
                return;
            }
            if (document.getElementById('catalog-books')) {
                const filtro = document.getElementById('category-filter');

                categories.forEach(categoria => {
                    filtro.add(new Option(categoria, categoria));
                });

                renderCatalog();
                return;
            }

            renderCategories();
            renderCatalog();
            renderAdminAll();
            renderWorkerMessages();
        };

        function navigateTo(sectionId) {
            document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active-link'));
            
            const link = document.getElementById('link-' + sectionId);
            if (link) link.classList.add('active-link');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function renderCatalog() {
            const featuredBox = document.getElementById('featured-books');
            const catalogBox = document.getElementById('catalog-books');
            if (featuredBox) featuredBox.innerHTML = '';
            if (catalogBox) catalogBox.innerHTML = '';

            books.forEach(book => {
                const isFav = wishlist.includes(book.id);
                const html = `
                    <div class="book-card">
                        <i class="${isFav ? 'fa-solid' : 'fa-regular'} fa-heart wishlist-icon" onclick="toggleWishlist('${book.id}')"></i>
                        <div class="placeholder-box book-card-img" onclick="openDetail('${book.id}')">PORTADA</div>
                        <h4 onclick="openDetail('${book.id}')" style="cursor:pointer;">${book.title}</h4>
                        <p style="font-size:0.8rem; color: var(--wf-gray);">${book.author}</p>
                        <div class="stars">⭐⭐⭐⭐⭐ (${book.rating})</div>
                        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color: #27ae60;">Gratis</strong>
                            <button class="btn" onclick="addToCart('${book.id}')"><i class="fa-solid fa-cart-shopping"></i></button>
                        </div>
                    </div>
                `;
               if (catalogBox) {
                    catalogBox.innerHTML += html;
                }

                if (featuredBox && featuredBox.children.length < 2) {
                    featuredBox.innerHTML += html;
                }
            });
        }

        function filterBooks() {
            const query = document.getElementById('global-search').value.toLowerCase();
            const cat = document.getElementById('category-filter').value;

            const cards = document.querySelectorAll('#catalog-books .book-card');
            cards.forEach((card, index) => {
                const b = books[index];
                const matchesSearch = b.title.toLowerCase().includes(query) || b.author.toLowerCase().includes(query) || b.category.toLowerCase().includes(query);
                const matchesCat = (cat === 'all' || b.category === cat);

                card.style.display = (matchesSearch && matchesCat) ? 'flex' : 'none';
            });
        }

        function openDetail(bookId) {
            const params = new URLSearchParams({
                id: bookId,
                origen: window.location.pathname
            });

            window.location.href = `detalle.html?${params}`;
        }

        function renderReviews(bookId) {
            const list = document.getElementById('reviews-list');
            list.innerHTML = '';
            const approved = reviews.filter(r => r.bookId === bookId && r.status === 'Aprobada');

            if (approved.length === 0) list.innerHTML = '<p>Sin reseñas disponibles.</p>';
            approved.forEach(r => {
                list.innerHTML += `
                    <div style="background:var(--wf-bg); padding:10px; border-radius:var(--radius); margin-bottom:10px;">
                        <strong>${r.userName}</strong> - <span>${'⭐'.repeat(r.rating)}</span>
                        <p>${r.text}</p>
                    </div>
                `;
            });

            const reviewBox = document.getElementById('add-review-box');
            if (!currentUser) {
                reviewBox.innerHTML = '<p style="color: var(--wf-gray);"><em>Inicia sesión para escribir una reseña.</em></p>';
            }
        }

        function submitReview(e) {
            e.preventDefault();
            if (!currentUser) return alert('Debes iniciar sesión.');
            const rating = parseInt(document.getElementById('review-rating').value);
            const text = document.getElementById('review-text').value;

            reviews.push({ id: 'r' + (reviews.length + 1), bookId: currentDetailBookId, userName: currentUser.name, rating, text, status: 'Pendiente' });
            alert('Reseña enviada exitosamente para revisión.');
            document.getElementById('review-text').value = '';
            renderAdminReviews();
        }

        function addToCart(bookId) {
            if (!currentUser) {
                alert('Por favor inicia sesión para seleccionar libros.');
                openAuthModal('login');
                return;
            }
            const b = books.find(x => x.id === bookId);
            cart.push(b);
            updateCartUI();
            alert(`"${b.title}" agregado a tu carrito.`);
        }

        function updateCartUI() {
            document.getElementById('cart-count').innerText = cart.length;
            const container = document.getElementById('cart-items');
            const summary = document.getElementById('cart-summary');
            container.innerHTML = '';

            if (cart.length === 0) {
                container.innerHTML = '<p>El carrito está vacío.</p>';
                summary.style.display = 'none';
                return;
            }

            cart.forEach((item, index) => {
                container.innerHTML += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:10px; border:1px solid var(--wf-border); margin-bottom:10px;">
                        <div><h4>${item.title}</h4><p style="color:#27ae60;">Gratis</p></div>
                        <button class="btn btn-danger" onclick="removeFromCart(${index})">Quitar</button>
                    </div>
                `;
            });
            summary.style.display = 'block';
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartUI();
        }

        function processFreeCheckout() {
            const orderId = 'ORD-' + Math.floor(1000 + Math.random() * 9000);
            
            cart.forEach(item => item.downloads += 1);

            orders.push({ id: orderId, userName: currentUser.name, total: 0, status: 'Descargado', date: new Date().toLocaleDateString() });
            cart = [];
            updateCartUI();
            alert(`¡Obtención exitosa! Los libros han sido añadidos a tu historial.`);
            renderOrders();
            renderReports();
            if (currentUser.role === 'author') renderAuthorDashboard();
            navigateTo('perfil');
            switchProfileTab('historial');
        }

        function toggleWishlist(bookId) {
            if (!currentUser) return alert('Debes iniciar sesión para guardar favoritos.');
            const idx = wishlist.indexOf(bookId);
            if (idx > -1) wishlist.splice(idx, 1);
            else wishlist.push(bookId);
            renderCatalog();
            renderWishlist();
        }

        function renderWishlist() {
            const box = document.getElementById('wishlist-books');
            box.innerHTML = '';
            const items = books.filter(b => wishlist.includes(b.id));
            if (items.length === 0) box.innerHTML = '<p>No tienes libros en tu lista de deseos.</p>';
            items.forEach(b => {
                box.innerHTML += `
                    <div class="book-card">
                        <h4>${b.title}</h4>
                        <p style="color:#27ae60;">Gratis</p>
                        <button class="btn btn-primary" onclick="addToCart('${b.id}')">Agregar al Carrito</button>
                    </div>
                `;
            });
        }

        function handleContactSubmit(e) {
            e.preventDefault();
            const name = document.getElementById('cnt-name').value;
            const email = document.getElementById('cnt-email').value;
            const subject = document.getElementById('cnt-subject').value;
            const message = document.getElementById('cnt-message').value;

            contactMessages.push({
                id: "MSG-" + Math.floor(100 + Math.random() * 900),
                name, email, subject, message, status: "Pendiente"
            });

            alert('Mensaje enviado. Un trabajador revisará tu caso en breve.');
            e.target.reset();
            renderWorkerMessages();
        }

        function renderWorkerMessages() {
            const table = document.getElementById('worker-messages-table');
            table.innerHTML = '';
            contactMessages.forEach((m, idx) => {
                table.innerHTML += `
                    <tr>
                        <td>${m.id}</td>
                        <td>${m.name}</td>
                        <td>${m.email}</td>
                        <td>${m.subject}</td>
                        <td>${m.message}</td>
                        <td><strong>${m.status}</strong></td>
                        <td>
                            ${m.status === 'Pendiente' ? `<button class="btn" onclick="attendMessage(${idx})">Atender / Responder</button>` : '<em>Atendido</em>'}
                        </td>
                    </tr>
                `;
            });
        }

        function attendMessage(idx) {
            contactMessages[idx].status = 'Atendido';
            alert(`El mensaje ${contactMessages[idx].id} ha sido marcado como Atendido.`);
            renderWorkerMessages();
        }

        function renderAuthorDashboard() {
            if (!currentUser || currentUser.role !== 'author') return;
            
            const myBooks = books.filter(b => b.author.toLowerCase() === currentUser.name.toLowerCase());
            const totalDownloads = myBooks.reduce((acc, b) => acc + b.downloads, 0);

            document.getElementById('author-my-books-count').innerText = myBooks.length;
            document.getElementById('author-my-downloads-count').innerText = totalDownloads;

            const table = document.getElementById('author-books-table');
            table.innerHTML = '';
            if (myBooks.length === 0) {
                table.innerHTML = '<tr><td colspan="6">No has publicado ningún libro aún.</td></tr>';
                return;
            }

            myBooks.forEach(b => {
                table.innerHTML += `
                    <tr>
                        <td>#${b.id}</td>
                        <td>${b.title}</td>
                        <td>${b.category}</td>
                        <td>${b.downloads}</td>
                        <td>⭐ ${b.rating}</td>
                        <td>
                            <button class="btn" onclick="editBook('${b.id}')">Editar</button>
                            <button class="btn btn-danger" onclick="deleteBook('${b.id}')">Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }

        function openAuthorBookModal() {
            openBookModal();
            document.getElementById('bm-author').value = currentUser.name;
            document.getElementById('bm-author').disabled = true;
        }

        function openAuthModal(type) {
            document.getElementById('authModal').style.display = 'flex';
            toggleAuthMode(type);
        }
        
        function closeAuthModal() { 
            document.getElementById('authModal').style.display = 'none'; 
        }

        function toggleAuthMode(type) {
            document.getElementById('loginForm').style.display = type === 'login' ? 'block' : 'none';
            document.getElementById('signupForm').style.display = type === 'signup' ? 'block' : 'none';
            document.getElementById('recoverForm').style.display = type === 'recover' ? 'block' : 'none';
        }

        function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const selectedRole = document.getElementById('user-role').value;
            
            let u = users.find(x => x.email === email);
            if (!u) {
                u = { name: roleLabels[selectedRole] || "Usuario", email: email, role: selectedRole };
                users.push(u);
            } else {
                u.role = selectedRole;
            }
            
            loginUser(u);
        }

        function handleSignUp(e) {
            e.preventDefault();
            const password = document.getElementById('signup-password');
            const confirmation = document.getElementById('signup-password-confirm');
            if (password.value !== confirmation.value) {
                alert('Las contraseñas no coinciden.');
                confirmation.focus();
                return;
            }

            const name = document.getElementById('signup-name').value;
            const email = document.getElementById('signup-email').value;
            const role = document.getElementById('signup-role').value;
            
            const u = { name, email, role };
            users.push(u);
            loginUser(u);
        }

        function handleRecoverPass(e) {
            e.preventDefault();
            const email = document.getElementById('recover-email').value;
            alert(`Se han enviado las instrucciones de recuperación al correo: ${email}`);
            toggleAuthMode('login');
        }

        function loginUser(u) {
            currentUser = u;
            closeAuthModal();
            document.getElementById('auth-buttons').style.display = 'none';
            document.getElementById('user-menu').style.display = 'flex';
            
            document.getElementById('user-display-name').innerText = roleLabels[u.role] || u.role;
            
            document.getElementById('admin-nav-item').style.display = u.role === 'admin' ? 'block' : 'none';
            document.getElementById('worker-nav-item').style.display = (u.role === 'worker' || u.role === 'admin') ? 'block' : 'none';
            document.getElementById('author-nav-item').style.display = (u.role === 'author' || u.role === 'admin') ? 'block' : 'none';

            if (u.role === 'admin') {
                navigateTo('admin-dashboard');
            } else if (u.role === 'author') {
                renderAuthorDashboard();
                navigateTo('author-dashboard');
            } else if (u.role === 'worker') {
                navigateTo('worker-dashboard');
            } else {
                alert('Sesión iniciada con éxito.');
            }
            
            document.getElementById('prof-name').value = u.name;
            document.getElementById('prof-email').value = u.email;
            document.getElementById('prof-role').value = roleLabels[u.role] || u.role;
            renderOrders();
            renderCatalog();
            renderReports();
        }

        function logout() {
            currentUser = null;
            document.getElementById('auth-buttons').style.display = 'flex';
            document.getElementById('user-menu').style.display = 'none';
            document.getElementById('admin-nav-item').style.display = 'none';
            document.getElementById('worker-nav-item').style.display = 'none';
            document.getElementById('author-nav-item').style.display = 'none';
            cart = []; wishlist = [];
            updateCartUI();
            navigateTo('inicio');
        }

        function switchProfileTab(tab) {
            document.querySelectorAll('.profile-tab').forEach(t => t.style.display = 'none');
            document.getElementById('tab-' + tab).style.display = 'block';
            document.querySelectorAll('#perfil [data-profile-tab]').forEach(boton => {
                boton.classList.toggle('active', boton.dataset.profileTab === tab);
            });
        }

        function saveProfile(e) {
            e.preventDefault();
            currentUser.name = document.getElementById('prof-name').value;
            alert('Perfil actualizado.');
        }

        function renderOrders() {
            const body = document.getElementById('order-history-body');
            body.innerHTML = '';
            if (!currentUser) return;
            const myOrders = orders.filter(o => o.userName === currentUser.name);
            if (myOrders.length === 0) {
                body.innerHTML = '<tr><td colspan="5">No registras lecturas o descargas previas.</td></tr>';
                return;
            }
            myOrders.forEach(o => {
                body.innerHTML += `<tr><td>${o.id}</td><td>${o.date}</td><td style="color:#27ae60;">Gratis</td><td>${o.status}</td><td><button class="btn" onclick="alert('Iniciando descarga digital del archivo PDF...')"><i class="fa-solid fa-download"></i> Descargar</button></td></tr>`;
            });
        }

        function switchAdminTab(tab, boton) {
            document.querySelectorAll('.admin-tab').forEach(t => t.style.display = 'none');
            document.getElementById(tab).style.display = 'block';
            document.querySelector('.tab-menu .tab-btn.active')?.classList.remove('active');
            boton.classList.add('active');
        }

        function renderAdminAll() {
            renderAdminBooks();
            renderCategories();
            renderAdminUsers();
            renderAdminReviews();
            renderReports();
        }

        function renderAdminBooks() {
            const body = document.getElementById('admin-books-table');
            body.innerHTML = '';
            books.forEach(b => {
                body.innerHTML += `
                    <tr>
                        <td>#${b.id}</td><td>${b.title}</td><td>${b.author}</td><td>${b.category}</td><td>${b.downloads}</td>
                        <td>
                            <button class="btn" onclick="editBook('${b.id}')">Editar</button>
                            <button class="btn btn-danger" onclick="deleteBook('${b.id}')">Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }

        function openBookModal() {
            document.getElementById('book-id').value = '';
            document.getElementById('bm-title').value = '';
            document.getElementById('bm-author').value = '';
            document.getElementById('bm-author').disabled = false;
            document.getElementById('bm-synopsis').value = '';
            document.getElementById('bookModal').style.display = 'flex';
        }
        function closeBookModal() { document.getElementById('bookModal').style.display = 'none'; }

        function editBook(id) {
            const b = books.find(x => x.id === id);
            
            if (currentUser.role === 'author' && b.author.toLowerCase() !== currentUser.name.toLowerCase()) {
                return alert('Solo puedes editar tus propios libros.');
            }

            document.getElementById('book-id').value = b.id;
            document.getElementById('bm-title').value = b.title;
            document.getElementById('bm-author').value = b.author;
            document.getElementById('bm-author').disabled = (currentUser.role === 'author');
            document.getElementById('bm-category').value = b.category;
            document.getElementById('bm-synopsis').value = b.synopsis;
            document.getElementById('bookModal').style.display = 'flex';
        }

        function saveBook(e) {
            e.preventDefault();
            const id = document.getElementById('book-id').value;
            const title = document.getElementById('bm-title').value;
            const author = document.getElementById('bm-author').value;
            const category = document.getElementById('bm-category').value;
            const synopsis = document.getElementById('bm-synopsis').value;

            if (id) {
                const b = books.find(x => x.id === id);
                Object.assign(b, { title, author, category, synopsis });
            } else {
                books.push({ id: (books.length + 1).toString(), title, author, category, downloads: 0, rating: "5.0", synopsis });
            }
            closeBookModal();
            renderAdminBooks();
            renderCatalog();
            renderReports();
            if (currentUser && currentUser.role === 'author') renderAuthorDashboard();
        }

        function deleteBook(id) {
            const b = books.find(x => x.id === id);
            if (currentUser.role === 'author' && b.author.toLowerCase() !== currentUser.name.toLowerCase()) {
                return alert('Solo puedes eliminar tus propios libros.');
            }

            if (confirm('¿Eliminar libro del catálogo?')) {
                books = books.filter(x => x.id !== id);
                renderAdminBooks();
                renderCatalog();
                renderReports();
                if (currentUser && currentUser.role === 'author') renderAuthorDashboard();
            }
        }

        function renderCategories() {
            const filter = document.getElementById('category-filter');
            const selectBM = document.getElementById('bm-category');
            const table = document.getElementById('admin-cats-table');
            
            filter.innerHTML = '<option value="all">Todas las Categorías</option>';
            selectBM.innerHTML = '';
            table.innerHTML = '';

            categories.forEach(c => {
                filter.innerHTML += `<option value="${c}">${c}</option>`;
                selectBM.innerHTML += `<option value="${c}">${c}</option>`;
                table.innerHTML += `<tr><td>${c}</td><td><button class="btn btn-danger" onclick="deleteCategory('${c}')">Eliminar</button></td></tr>`;
            });
        }

        function addCategory(e) {
            e.preventDefault();
            const cat = document.getElementById('new-cat-input').value.trim();
            if (cat && !categories.includes(cat)) {
                categories.push(cat);
                document.getElementById('new-cat-input').value = '';
                renderCategories();
                renderReports();
            }
        }

        function deleteCategory(cat) {
            categories = categories.filter(c => c !== cat);
            renderCategories();
            renderReports();
        }

        function renderAdminUsers() {
            const table = document.getElementById('admin-users-table');
            table.innerHTML = '';
            users.forEach((u, i) => {
                table.innerHTML += `
                    <tr>
                        <td>${u.name}</td><td>${u.email}</td><td>${roleLabels[u.role] || u.role}</td>
                        <td><button class="btn" onclick="toggleUserRole(${i})">Cambiar Rol</button></td>
                    </tr>
                `;
            });
        }

        function toggleUserRole(index) {
            const roles = ['user', 'author', 'worker', 'admin'];
            const currentIdx = roles.indexOf(users[index].role);
            users[index].role = roles[(currentIdx + 1) % roles.length];
            renderAdminUsers();
        }

        function renderAdminReviews() {
            const table = document.getElementById('admin-reviews-table');
            table.innerHTML = '';
            reviews.forEach((r, idx) => {
                const b = books.find(x => x.id === r.bookId);
                table.innerHTML += `
                    <tr>
                        <td>${b ? b.title : 'N/A'}</td>
                        <td>${r.userName}</td>
                        <td>${r.text}</td>
                        <td><strong>${r.status}</strong></td>
                        <td>
                            <button class="btn" onclick="approveReview(${idx})">Aprobar</button>
                            <button class="btn btn-danger" onclick="deleteReview(${idx})">Eliminar</button>
                        </td>
                    </tr>
                `;
            });
        }

        function approveReview(idx) {
            reviews[idx].status = 'Aprobada';
            renderAdminReviews();
        }

        function deleteReview(idx) {
            reviews.splice(idx, 1);
            renderAdminReviews();
        }

        function renderReports() {
            const totalDownloads = books.reduce((acc, b) => acc + b.downloads, 0);

            document.getElementById('stat-total-downloads').innerText = totalDownloads;
            document.getElementById('stat-total-books').innerText = books.length;
            document.getElementById('stat-total-categories').innerText = categories.length;
            document.getElementById('stat-total-users').innerText = users.length;

            const categoryTable = document.getElementById('admin-category-reports-table');
            categoryTable.innerHTML = '';

            categories.forEach(cat => {
                const booksInCat = books.filter(b => b.category === cat);
                const downloadsInCat = booksInCat.reduce((acc, b) => acc + b.downloads, 0);

                categoryTable.innerHTML += `
                    <tr>
                        <td><strong>${cat}</strong></td>
                        <td>${booksInCat.length}</td>
                        <td><strong>${downloadsInCat}</strong> descargas</td>
                    </tr>
                `;
            });

            const table = document.getElementById('admin-reports-table');
            table.innerHTML = '';
            
            const sortedBooks = [...books].sort((a, b) => b.downloads - a.downloads);
            sortedBooks.forEach(b => {
                table.innerHTML += `
                    <tr>
                        <td>${b.title}</td>
                        <td>${b.author}</td>
                        <td>${b.category}</td>
                        <td><strong>${b.downloads}</strong> descargas</td>
                        <td>⭐ ${b.rating}</td>
                    </tr>
                `;
            });
        }
        function validarRegistro(e) {
            const password = document.getElementById('signup-password');
            const confirmation = document.getElementById('signup-password-confirm');
            if (password.value !== confirmation.value) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                confirmation.focus();
                return false;
            }
            return true;
        }
        
        window.onload = function () {
            if (document.getElementById('catalog-books')) {
                const filtro = document.getElementById('category-filter');
                categories.forEach(categoria => {
                    filtro.add(new Option(categoria, categoria));
                });
                renderCatalog();
                return;
            }

            if (document.getElementById('admin-books-table')) {
                renderCategories();
                renderCatalog();
                renderAdminAll();
            }
            if (document.getElementById('worker-messages-table')) {
                renderWorkerMessages();
            }
        };