document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-chat]').forEach((chat) => {
    const messages = chat.querySelector('[data-chat-messages]');
    const form = chat.querySelector('[data-chat-form]');
    const feedUrl = chat.dataset.feedUrl;
    if (!messages || !feedUrl) return;
    let polling = false;

    const append = (items) => {
      items.forEach((item) => {
        if (messages.querySelector(`[data-message-id="${item.id}"]`)) return;
        messages.insertAdjacentHTML('beforeend', item.html);
        messages.dataset.lastMessageId = String(item.id);
      });
      if (items.length) messages.scrollTop = messages.scrollHeight;
    };

    const poll = async () => {
      if (polling || document.hidden) return;
      polling = true;
      try {
        const separator = feedUrl.includes('?') ? '&' : '?';
        const response = await fetch(`${feedUrl}${separator}after=${messages.dataset.lastMessageId || 0}`, {
          headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (payload.ok) append(payload.messages || []);
      } catch (_) {
        // La prochaine interrogation retentera automatiquement.
      } finally {
        polling = false;
      }
    };

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      chat.classList.add('is-sending');
      chat.querySelector('.site-chat-error')?.remove();
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: new FormData(form),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Envoi impossible.');
        form.reset();
        form.querySelector('[data-finea-file-preview]')?.replaceChildren();
        await poll();
      } catch (error) {
        form.insertAdjacentHTML('beforebegin', `<div class="site-chat-error">${escapeChatHtml(error.message)}</div>`);
      } finally {
        chat.classList.remove('is-sending');
      }
    });

    const voiceButton = form?.querySelector('[data-voice-record]');
    let recorder;
    let audioChunks = [];
    voiceButton?.addEventListener('click', async () => {
      if (recorder?.state === 'recording') {
        recorder.stop();
        voiceButton.textContent = 'Préparation de la note vocale…';
        return;
      }
      if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
        voiceButton.textContent = 'Enregistrement non pris en charge';
        return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        audioChunks = [];
        recorder = new MediaRecorder(stream);
        recorder.addEventListener('dataavailable', (event) => {
          if (event.data.size) audioChunks.push(event.data);
        });
        recorder.addEventListener('stop', () => {
          const blob = new Blob(audioChunks, { type: recorder.mimeType || 'audio/webm' });
          const extension = blob.type.includes('ogg') ? 'ogg' : 'webm';
          const file = new File([blob], `note-vocale-${Date.now()}.${extension}`, { type: blob.type });
          const transfer = new DataTransfer();
          transfer.items.add(file);
          const input = form.querySelector('input[type="file"][name="attachment"]');
          if (input) {
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
          stream.getTracks().forEach((track) => track.stop());
          voiceButton.textContent = 'Note vocale prête ✓';
        });
        recorder.start();
        voiceButton.textContent = 'Arrêter l’enregistrement';
      } catch (_) {
        voiceButton.textContent = 'Micro non autorisé';
      }
    });

    messages.scrollTop = messages.scrollHeight;
    window.setInterval(poll, 4000);
  });
});

function escapeChatHtml(value) {
  return String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[character]));
}
