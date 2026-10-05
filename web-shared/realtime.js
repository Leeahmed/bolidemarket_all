// Transport only. Consumers reconcile through the authorized REST API.
export const eventTypes = ["PriceOfferCreated","PriceOfferAccepted","PriceOfferRejected","PriceOfferConsumed","VehicleCreated","VehicleUpdated","VehicleStatusChanged","VehiclePublished","VehicleUnpublished","VehicleImageUpdated","VehicleAvailabilityChanged","ReservationCreated","ReservationConfirmed","ReservationCancelled","ReservationRejected","ReservationStarted","ReservationCompleted","ReservationExpired","ReservationUpdated","OrderCreated","OrderConfirmed","OrderCancelled","OrderCompleted","OrderUpdated"]
export function createRealtime({ Echo, Pusher, post, env }) {
  let echo, identity = '', generation = 0
  const listeners = new Map(), seen = new Set()
  function on(scope, callback) {
    if (!listeners.has(scope)) listeners.set(scope, new Set())
    listeners.get(scope).add(callback)
    return () => listeners.get(scope)?.delete(callback)
  }
  function deliver(scope, event) {
    const current = generation
    for (const cb of listeners.get(scope) || []) Promise.resolve().then(() => { if(current === generation) return cb(event) }).catch(() => {})
  }
  function receive(scope, event) {
    if (!event?.event_id || !event.data || !eventTypes.includes(event.type)) return
    const key = scope + ':' + event.event_id
    if (seen.has(key)) return
    seen.add(key)
    if (seen.size > 1000) seen.delete(seen.values().next().value)
    deliver(scope, event)
  }
  function disconnect() {
    generation++; identity = ''; seen.clear()
    echo?.disconnect(); echo = undefined
  }
  function connect({ userId, merchantId, publicChannel = false } = {}) {
    const next = [userId || '', merchantId || '', publicChannel].join(':')
    if (echo && identity === next) return
    disconnect()
    identity = next
    if (!env.VITE_REVERB_APP_KEY) return
    const current = generation
    echo = new Echo({
      broadcaster: 'reverb', Pusher, key: env.VITE_REVERB_APP_KEY,
      wsHost: env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(env.VITE_REVERB_PORT || 8080), wssPort: Number(env.VITE_REVERB_PORT || 443),
      forceTLS: ['https','wss'].includes(env.VITE_REVERB_SCHEME), enabledTransports: ['ws','wss'],
      disableStats: true,
      authorizer: channel => ({
        authorize: (socketId, callback) => post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, { quiet401: true })
          .then(data => { if (current === generation) callback(null, data) })
          .catch(error => { if (current === generation) callback(error, null) }),
      }),
    })
    function subscribe(channel, scope) {
      for (const type of eventTypes) channel.listen('.' + type, event => {
        if (current === generation) receive(scope, event)
      })
      // Subscription success also fires after reconnect. Re-read REST to close any gap.
      channel.subscribed(() => { if (current === generation) deliver(scope, { type: 'Reconnected', data: {} }) })
    }
    if (publicChannel) subscribe(echo.channel('marketplace'), 'public')
    if (userId) subscribe(echo.private('user.' + userId), 'private')
    if (userId && merchantId) subscribe(echo.private('merchant.' + merchantId), 'merchant')
  }
  return { connect, disconnect, on }
}
