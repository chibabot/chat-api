<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat API Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body>
    <div id="app" class="container">
        <header class="py-3 mb-4 border-bottom">
            <div class="d-flex align-items-center justify-content-between">
                <h1 class="h4 mb-0">Chat API Admin Panel</h1>
                <div v-if="user">
                    <span class="me-3">{{ user.name }}</span>
                    <button class="btn btn-sm btn-outline-danger" @click="logout">Logout</button>
                </div>
            </div>
        </header>

        <div v-if="!isAuthenticated" class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Login</div>
                    <div class="card-body">
                        <div v-if="loginError" class="alert alert-danger">{{ loginError }}</div>
                        <form @submit.prevent="login">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" v-model="loginForm.email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" v-model="loginForm.password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isAuthenticated" class="row">
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">Dashboard</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card text-center mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Users</h5>
                                        <p class="card-text display-4">{{ stats.total_users || 0 }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Chats</h5>
                                        <p class="card-text display-4">{{ stats.total_chats || 0 }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Messages</h5>
                                        <p class="card-text display-4">{{ stats.total_messages || 0 }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mb-4">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chats-tab" data-bs-toggle="tab" data-bs-target="#chats" type="button" role="tab" aria-controls="chats" aria-selected="true" @click="loadChats">Chats</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="false" @click="loadUsers">Users</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="chats" role="tabpanel" aria-labelledby="chats-tab">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Chats</span>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">New Chat</button>
                            </div>
                            <div class="card-body">
                                <div v-if="chats.length === 0" class="text-center p-3">
                                    <p>No chats found</p>
                                </div>
                                <table v-else class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Last Message</th>
                                            <th>Last Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="chat in chats" :key="chat.id">
                                            <td>{{ chat.id }}</td>
                                            <td>{{ chat.title }}</td>
                                            <td>{{ chat.last_message_preview || 'No messages' }}</td>
                                            <td>{{ formatDate(chat.last_message_time) }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-info me-1" @click="viewChat(chat.id)">View</button>
                                                <button class="btn btn-sm btn-danger" @click="deleteChat(chat.id)">Delete</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <nav v-if="chats.length > 0">
                                    <ul class="pagination">
                                        <li v-for="page in chatPages" :key="page" :class="['page-item', { active: page === currentChatPage }]">
                                            <a class="page-link" href="#" @click.prevent="loadChats(page)">{{ page }}</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="users" role="tabpanel" aria-labelledby="users-tab">
                        <div class="card">
                            <div class="card-header">Users</div>
                            <div class="card-body">
                                <div v-if="users.length === 0" class="text-center p-3">
                                    <p>No users found</p>
                                </div>
                                <table v-else class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="user in users" :key="user.id">
                                            <td>{{ user.id }}</td>
                                            <td>{{ user.name }}</td>
                                            <td>{{ user.email }}</td>
                                            <td>{{ formatDate(user.created_at) }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-danger" @click="deleteUser(user.id)">Delete</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <nav v-if="users.length > 0">
                                    <ul class="pagination">
                                        <li v-for="page in userPages" :key="page" :class="['page-item', { active: page === currentUserPage }]">
                                            <a class="page-link" href="#" @click.prevent="loadUsers(page)">{{ page }}</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Details Modal -->
        <div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="chatModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="chatModalLabel">Chat: {{ currentChat.title }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="!currentChat.id" class="text-center">
                            <p>Loading chat details...</p>
                        </div>
                        <div v-else>
                            <div class="mb-3">
                                <label for="messageContent" class="form-label">New Message</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="messageContent" v-model="newMessage" placeholder="Type a message...">
                                    <button class="btn btn-primary" @click="sendMessage">Send</button>
                                </div>
                            </div>
                            <hr>
                            <div v-if="messages.length === 0" class="text-center">
                                <p>No messages in this chat</p>
                            </div>
                            <div v-else class="messages">
                                <div v-for="message in messages" :key="message.id" class="card mb-2">
                                    <div class="card-body">
                                        <p class="mb-1">{{ message.content }}</p>
                                        <small class="text-muted">{{ formatDate(message.created_at) }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Chat Modal -->
        <div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newChatModalLabel">Create New Chat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="createChat">
                            <div class="mb-3">
                                <label for="chatTitle" class="form-label">Chat Title</label>
                                <input type="text" class="form-control" id="chatTitle" v-model="newChatTitle" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp, ref, onMounted, computed } = Vue;

        createApp({
            setup() {
                const isAuthenticated = ref(false);
                const loginForm = ref({ email: '', password: '' });
                const loginError = ref('');
                const user = ref(null);
                const token = ref(localStorage.getItem('token') || '');
                
                const chats = ref([]);
                const users = ref([]);
                const messages = ref([]);
                const stats = ref({});
                const currentChat = ref({});
                const newMessage = ref('');
                const newChatTitle = ref('');

                const currentChatPage = ref(1);
                const totalChatPages = ref(1);
                const currentUserPage = ref(1);
                const totalUserPages = ref(1);

                const chatPages = computed(() => {
                    const pages = [];
                    for (let i = 1; i <= totalChatPages.value; i++) {
                        pages.push(i);
                    }
                    return pages;
                });

                const userPages = computed(() => {
                    const pages = [];
                    for (let i = 1; i <= totalUserPages.value; i++) {
                        pages.push(i);
                    }
                    return pages;
                });

                // Check if user is already authenticated
                onMounted(() => {
                    if (token.value) {
                        getUser();
                    }
                });

                const login = async () => {
                    try {
                        const response = await axios.post('/api/auth/login', loginForm.value);
                        token.value = response.data.access_token;
                        localStorage.setItem('token', token.value);
                        user.value = response.data.user;
                        isAuthenticated.value = true;
                        loginError.value = '';
                        
                        // Initialize dashboard data
                        getStats();
                        loadChats();
                    } catch (error) {
                        console.error('Login error:', error);
                        loginError.value = error.response?.data?.error || 'Login failed';
                    }
                };

                const logout = async () => {
                    try {
                        await axios.post('/api/auth/logout', {}, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                    } catch (error) {
                        console.error('Logout error:', error);
                    } finally {
                        localStorage.removeItem('token');
                        token.value = '';
                        user.value = null;
                        isAuthenticated.value = false;
                    }
                };

                const getUser = async () => {
                    try {
                        const response = await axios.get('/api/auth/me', {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        user.value = response.data;
                        isAuthenticated.value = true;
                        
                        // Initialize dashboard data
                        getStats();
                        loadChats();
                    } catch (error) {
                        console.error('Get user error:', error);
                        localStorage.removeItem('token');
                        token.value = '';
                    }
                };

                const getStats = async () => {
                    try {
                        const response = await axios.get('/api/admin/dashboard', {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        stats.value = response.data;
                    } catch (error) {
                        console.error('Get stats error:', error);
                    }
                };

                const loadChats = async (page = 1) => {
                    try {
                        currentChatPage.value = page;
                        const response = await axios.get(`/api/admin/chats?page=${page}`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        chats.value = response.data.data;
                        totalChatPages.value = response.data.last_page;
                    } catch (error) {
                        console.error('Load chats error:', error);
                    }
                };

                const loadUsers = async (page = 1) => {
                    try {
                        currentUserPage.value = page;
                        const response = await axios.get(`/api/admin/users?page=${page}`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        users.value = response.data.data;
                        totalUserPages.value = response.data.last_page;
                    } catch (error) {
                        console.error('Load users error:', error);
                    }
                };

                const viewChat = async (chatId) => {
                    try {
                        const response = await axios.get(`/api/chats/${chatId}`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        currentChat.value = response.data;
                        
                        // Load messages
                        const msgResponse = await axios.get(`/api/chats/${chatId}/messages`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        messages.value = msgResponse.data.data;
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('chatModal')).show();
                    } catch (error) {
                        console.error('View chat error:', error);
                    }
                };

                const sendMessage = async () => {
                    if (!newMessage.value.trim()) return;
                    
                    try {
                        await axios.post(`/api/chats/${currentChat.value.id}/messages`, {
                            content: newMessage.value
                        }, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        
                        // Reload messages
                        const response = await axios.get(`/api/chats/${currentChat.value.id}/messages`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        messages.value = response.data.data;
                        
                        // Clear input
                        newMessage.value = '';
                        
                        // Reload chat list to update last message
                        loadChats(currentChatPage.value);
                    } catch (error) {
                        console.error('Send message error:', error);
                    }
                };

                const createChat = async () => {
                    if (!newChatTitle.value.trim()) return;
                    
                    try {
                        await axios.post('/api/chats', {
                            title: newChatTitle.value
                        }, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        
                        // Reload chats
                        loadChats();
                        
                        // Close modal and clear input
                        bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                        newChatTitle.value = '';
                        
                        // Update stats
                        getStats();
                    } catch (error) {
                        console.error('Create chat error:', error);
                    }
                };

                const deleteChat = async (chatId) => {
                    if (!confirm('Are you sure you want to delete this chat?')) return;
                    
                    try {
                        await axios.delete(`/api/chats/${chatId}`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        
                        // Reload chats
                        loadChats(currentChatPage.value);
                        
                        // Update stats
                        getStats();
                    } catch (error) {
                        console.error('Delete chat error:', error);
                    }
                };

                const deleteUser = async (userId) => {
                    if (!confirm('Are you sure you want to delete this user?')) return;
                    
                    try {
                        await axios.delete(`/api/admin/users/${userId}`, {
                            headers: { Authorization: `Bearer ${token.value}` }
                        });
                        
                        // Reload users
                        loadUsers(currentUserPage.value);
                        
                        // Update stats
                        getStats();
                    } catch (error) {
                        console.error('Delete user error:', error);
                    }
                };

                const formatDate = (dateString) => {
                    if (!dateString) return 'N/A';
                    const date = new Date(dateString);
                    return date.toLocaleString();
                };

                return {
                    isAuthenticated,
                    loginForm,
                    loginError,
                    user,
                    chats,
                    users,
                    messages,
                    stats,
                    currentChat,
                    newMessage,
                    newChatTitle,
                    currentChatPage,
                    currentUserPage,
                    chatPages,
                    userPages,
                    login,
                    logout,
                    loadChats,
                    loadUsers,
                    viewChat,
                    sendMessage,
                    createChat,
                    deleteChat,
                    deleteUser,
                    formatDate
                };
            }
        }).mount('#app');
    </script>
</body>
</html> 