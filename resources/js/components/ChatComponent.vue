<template>
  <div>
    <div v-for="(msg, index) in messages" :key="index">
      <strong>{{ msg.user.name }}:</strong> {{ msg.message }}
    </div>
    <input v-model="newMessage" @keyup.enter="sendMessage" placeholder="Tape ton message..." />
  </div>
</template>

<script>
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: process.env.MIX_PUSHER_APP_KEY,
  wsHost: window.location.hostname,
  wsPort: 6001,
  forceTLS: false,
  disableStats: true,
});

export default {
  data() {
    return {
      messages: [],
      newMessage: '',
    };
  },
  mounted() {
    Echo.channel('chat-global')
        .listen('MessageSent', (e) => {
          this.messages.push({
            user: e.user,
            message: e.message,
          });
        });
  },
  methods: {
    sendMessage() {
      axios.post('/api/chat/send', {
        message: this.newMessage,
      }).then(() => {
        this.newMessage = '';
      });
    },
  },
};
</script>
