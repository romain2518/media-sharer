const messagingCallTimer = {
    timerElm: document.querySelector('.call .timer span'),
    timerIntervalId: null,
    time: new Date(null),
    start: function () {
        if (null !== this.timerIntervalId) return;

        this.timerIntervalId = setInterval(() => {
            this.time.setSeconds(this.time.getSeconds()+1);
            if (this.time.getSeconds() < 3600) { // MM:SS
                this.timerElm.textContent = this.time.toISOString().slice(14, 19);
            } else { // HH:MM
                this.timerElm.textContent = this.time.toISOString().slice(11, 16);
            }
        }, 1000);
    },
    stop: function (delay) {
        clearInterval(this.timerIntervalId);
        this.timerIntervalId = null;
        this.time = new Date(null);

        setTimeout(() => {
            this.timerElm.textContent = '00:00';
        }, delay);
    },  
};

export default messagingCallTimer;
