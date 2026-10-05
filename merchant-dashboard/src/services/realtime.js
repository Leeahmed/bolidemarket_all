import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { post } from './api'
import { createRealtime } from '../../../web-shared/realtime'
export const realtime = createRealtime({ Echo, Pusher, post, env: import.meta.env.MODE === 'test' ? {} : import.meta.env })
