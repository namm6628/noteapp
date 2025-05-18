import { openDB } from 'https://cdn.jsdelivr.net/npm/idb@7/+esm';

window.dbPromise = openDB('notes-db', 1, {
  upgrade(db) {
    if (!db.objectStoreNames.contains('notes')) {
      db.createObjectStore('notes', { keyPath: 'id', autoIncrement: true });
    }
  }
});

const noteTextarea = document.getElementById('note');
const syncStatus = document.getElementById('syncStatus');

let saveTimeout = null;
let syncInProgress = false;
let retryTimeout = null;

function setSyncStatus(text, type = 'info') {
  if (!syncStatus) return;
  syncStatus.textContent = text;
  syncStatus.className = 'sync-status ' + type;
}

async function registerBackgroundSync() {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    try {
      const registration = await navigator.serviceWorker.ready;
      await registration.sync.register('sync-notes');
      console.log('Đăng ký background sync thành công');
    } catch (error) {
      console.log('Đăng ký background sync thất bại:', error);
    }
  }
}


window.saveNoteOffline = async function(note) {
  const db = await window.dbPromise;
  await db.put('notes', note);
  console.log('Đã lưu ghi chú offline:', note);
  registerBackgroundSync(); // <-- gọi đăng ký background sync ngay sau khi lưu
};


window.saveNoteOfflineDebounced = function(note) {
  clearTimeout(saveTimeout);
  saveTimeout = setTimeout(() => {
    window.saveNoteOffline(note);
  }, 1000);
};

window.syncNotesToServer = async function() {
  if (syncInProgress) return;
  if (!navigator.onLine) {
    setSyncStatus('Mất kết nối mạng, chờ online để đồng bộ', 'error');
    scheduleRetry();
    return;
  }

  syncInProgress = true;
  setSyncStatus('Đang đồng bộ ghi chú...', 'info');

  const db = await window.dbPromise;
  const notes = await db.getAll('notes');
  let allSuccess = true;

  for (const note of notes) {
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 8000);

      const response = await fetch('/note_app/sync_note.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(note),
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      if (!response.ok) throw new Error('Server trả về lỗi ' + response.status);
      const result = await response.json();
      if (result.success) {
        await db.delete('notes', note.id);
        console.log('Đồng bộ thành công note id=' + note.id);
      } else {
        allSuccess = false;
        console.error('Lỗi server khi đồng bộ note id=' + note.id + ': ' + result.message);
      }
    } catch (error) {
      allSuccess = false;
      console.error('Lỗi fetch note id=' + note.id + ':', error);
    }
  }

  if (allSuccess) {
    setSyncStatus('Đồng bộ ghi chú thành công', 'success');
    clearRetry();
  } else {
    setSyncStatus('Có lỗi khi đồng bộ, sẽ thử lại sau', 'error');
    scheduleRetry();
  }

  syncInProgress = false;
};

function scheduleRetry() {
  if (retryTimeout) return;
  retryTimeout = setTimeout(() => {
    retryTimeout = null;
    window.syncNotesToServer();
  }, 5000);
}

function clearRetry() {
  if (retryTimeout) {
    clearTimeout(retryTimeout);
    retryTimeout = null;
  }
}

// Tự động đồng bộ khi online lại
window.addEventListener('online', () => {
  setSyncStatus('Mạng online, bắt đầu đồng bộ...', 'info');
  window.syncNotesToServer();
});

// Định kỳ đồng bộ (3 phút 1 lần)
setInterval(() => {
  if (navigator.onLine) {
    window.syncNotesToServer();
  }
}, 3 * 60 * 1000);

// Gọi lưu offline debounce khi user nhập
noteTextarea?.addEventListener('input', () => {
  const note = {
    content: noteTextarea.value,
    updatedAt: new Date().toISOString()
  };
  window.saveNoteOfflineDebounced(note);
});

// Lắng nghe message từ service worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data === 'sync') {
      window.syncNotesToServer();
    }
  });
}

// UI effects: highlight nút khi click
document.querySelectorAll("button, .btn").forEach(btn => {
  btn.addEventListener("click", () => {
    btn.classList.add("active");
    setTimeout(() => btn.classList.remove("active"), 150);
  });
});

// Ripple effect on buttons
document.querySelectorAll(".btn, button").forEach(btn => {
  btn.addEventListener("click", function (e) {
    const circle = document.createElement("span");
    circle.classList.add("ripple");
    this.appendChild(circle);

    const d = Math.max(this.clientWidth, this.clientHeight);
    circle.style.width = circle.style.height = d + 'px';
    circle.style.left = (e.clientX - this.getBoundingClientRect().left - d/2) + 'px';
    circle.style.top = (e.clientY - this.getBoundingClientRect().top - d/2) + 'px';

    setTimeout(() => circle.remove(), 600);
  });
});

// Toggle dark mode thủ công
document.getElementById("toggleDarkMode")?.addEventListener("click", () => {
  document.body.classList.toggle("dark-mode");
  localStorage.setItem("dark-mode", document.body.classList.contains("dark-mode"));
});

// Tự bật dark mode nếu đã bật trước đó
if (localStorage.getItem("dark-mode") === "true") {
  document.body.classList.add("dark-mode");
}

// Mở modal với animation
document.querySelectorAll("[data-toggle='modal']").forEach(btn => {
  btn.addEventListener("click", () => {
    const target = btn.dataset.target;
    document.querySelector(target)?.classList.add("show");
  });
});
document.querySelectorAll(".modal .btn-close").forEach(closeBtn => {
  closeBtn.addEventListener("click", () => {
    closeBtn.closest(".modal").classList.remove("show");
  });
});

// Fade in notes on render
window.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".note-item").forEach((el, i) => {
    el.classList.add("fade-in");
    el.style.animationDelay = (i * 50) + "ms";
  });
});
