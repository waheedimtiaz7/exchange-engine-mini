# Mini Exchange Engine (Laravel + Vue 3)

A simplified **limit-order exchange engine** built with **Laravel** (API + matching engine) and **Vue 3** (Composition API) with **real-time updates** via Pusher.


Tech Stack

- **Backend:** Laravel (API, Jobs, Queues, Broadcasting)
- **Frontend:** Vue 3 (Composition API), Tailwind CSS, Vite
- **Database:** MySQL
- **Queue:** Database queue
- **Realtime:** Pusher + Laravel Echo
- **Auth:** Laravel Sanctum


## 🚀 Setup Instructions

1. Clone the repository

`git clone https://github.com/waheedimtiaz7/mini-exchange.git`
cd mini-exchange


2. Install backend dependencies
`composer install`


3. Environment configuration
cp .env.example .env
`php artisan key:generate`


4. Configure database:
`DB_DATABASE=mini_exchange
DB_USERNAME=root
DB_PASSWORD=`


5. Configure Pusher (required for real-time)

Create a free Pusher app and set:

BROADCAST_DRIVER=pusher

PUSHER_APP_ID=xxxx
PUSHER_APP_KEY=xxxx
PUSHER_APP_SECRET=xxxx
PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_KEY=xxxx
VITE_PUSHER_CLUSTER=mt1


6. Run migrations & seeders
`php artisan migrate`
`php artisan db:seed`

Seeders create:
- Sample users (buyer & seller)
- BTC / ETH assets
- Initial balances


7. Install frontend dependencies
`npm install`
`npm run dev`


8. Start services
`php artisan serve`


9. Run queue worker (required for matching):
`php artisan queue:work`


10. Access application
`http://127.0.0.1:8000`


Login using seeded credentials (see `UserSeeder`).



