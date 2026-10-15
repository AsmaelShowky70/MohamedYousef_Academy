    </main>
</div>

<!-- Admin Toast Notification Utility -->
<div id="toast-container" class="fixed top-5 left-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<script>
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-5 left-5 z-[9999] flex flex-col gap-2 pointer-events-none';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-red-600' : 'bg-slate-800');
    const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-triangle-exclamation' : 'fa-info-circle');
    
    toast.className = `${bgClass} text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm font-bold pointer-events-auto transform transition-all duration-300 -translate-x-10 opacity-0`;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i><span>${message}</span>`;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('-translate-x-10', 'opacity-0');
    }, 20);
    
    setTimeout(() => {
        toast.classList.add('-translate-x-10', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
</script>

</body>
</html>
