<?php
require_once 'includes/config.php';
$pageTitle = "Smart Trip Planning Platform for Sri Lanka Travel";
include 'includes/header.php';
?>

      <section class="relative h-screen flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0"><img alt="Sri Lanka Landscape" class="w-full h-full object-cover"
            src="assets/images/hero.jpg">
          <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/30 to-black/40"></div>
        </div>
        <div class="relative z-10 text-center px-4 max-w-4xl mx-auto w-full">
          <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight">Plan Your Perfect Sri Lankan
            Adventure</h1>
          <p class="text-xl text-white/90 mb-8 max-w-2xl mx-auto">Smart itinerary building, seamless bookings, and
            real-time optimization for unforgettable journeys</p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center"><a
              class="px-8 py-4 bg-teal-600 text-white text-base font-semibold rounded-full hover:bg-teal-700 transition-all shadow-lg hover:shadow-xl whitespace-nowrap cursor-pointer"
              href="customer/plan_trip.php" data-discover="true">Start Planning Now</a><a
              class="px-8 py-4 bg-white/10 backdrop-blur-sm text-white text-base font-semibold rounded-full hover:bg-white/20 transition-all border-2 border-white/30 whitespace-nowrap cursor-pointer"
              href="customer/dashboard.php" data-discover="true">View Dashboard</a></div>
        </div>
      </section>
      <section class="py-20 px-4">
        <div class="max-w-7xl mx-auto">
          <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose TripSync?</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">Experience the future of travel planning with our
              innovative features</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div
              class="bg-white p-8 rounded-2xl border border-gray-200 hover:border-teal-500 hover:shadow-xl transition-all">
              <div class="w-14 h-14 flex items-center justify-center bg-teal-100 rounded-2xl mb-6"><i
                  class="ri-route-line text-3xl text-teal-600"></i></div>
              <h3 class="text-xl font-semibold text-gray-900 mb-3">Smart Route Optimizer</h3>
              <p class="text-gray-600 text-sm leading-relaxed">AI-powered route planning that minimizes travel time and
                maximizes your experience</p>
            </div>
            <div
              class="bg-white p-8 rounded-2xl border border-gray-200 hover:border-teal-500 hover:shadow-xl transition-all">
              <div class="w-14 h-14 flex items-center justify-center bg-teal-100 rounded-2xl mb-6"><i
                  class="ri-hotel-line text-3xl text-teal-600"></i></div>
              <h3 class="text-xl font-semibold text-gray-900 mb-3">Integrated Marketplace</h3>
              <p class="text-gray-600 text-sm leading-relaxed">Book hotels and vehicles with contextual recommendations
                based on your itinerary</p>
            </div>
            <div
              class="bg-white p-8 rounded-2xl border border-gray-200 hover:border-teal-500 hover:shadow-xl transition-all">
              <div class="w-14 h-14 flex items-center justify-center bg-teal-100 rounded-2xl mb-6"><i
                  class="ri-map-pin-line text-3xl text-teal-600"></i></div>
              <h3 class="text-xl font-semibold text-gray-900 mb-3">Drag &amp; Drop Planner</h3>
              <p class="text-gray-600 text-sm leading-relaxed">Intuitive itinerary builder with real-time map preview
                and day-wise organization</p>
            </div>
            <div
              class="bg-white p-8 rounded-2xl border border-gray-200 hover:border-teal-500 hover:shadow-xl transition-all">
              <div class="w-14 h-14 flex items-center justify-center bg-teal-100 rounded-2xl mb-6"><i
                  class="ri-wallet-3-line text-3xl text-teal-600"></i></div>
              <h3 class="text-xl font-semibold text-gray-900 mb-3">Digital Travel Wallet</h3>
              <p class="text-gray-600 text-sm leading-relaxed">Manage all bookings and payments in one place with QR
                code access</p>
            </div>
          </div>
        </div>
      </section>
      <section class="py-20 px-4 bg-gradient-to-br from-gray-50 to-teal-50">
        <div class="max-w-7xl mx-auto">
          <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Popular Destinations</h2>
            <p class="text-lg text-gray-600">Discover the beauty of Sri Lanka</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div
              class="group relative overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all cursor-pointer">
              <div class="w-full h-80"><img alt="Sigiriya"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                  src="assets/images/sigiriya.jpg"></div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end">
                <h3 class="text-2xl font-bold text-white p-6">Sigiriya</h3>
              </div>
            </div>
            <div
              class="group relative overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all cursor-pointer">
              <div class="w-full h-80"><img alt="Ella"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                  src="assets/images/ella.jpg"></div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end">
                <h3 class="text-2xl font-bold text-white p-6">Ella</h3>
              </div>
            </div>
            <div
              class="group relative overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all cursor-pointer">
              <div class="w-full h-80"><img alt="Galle Fort"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                  src="assets/images/galle.jpg"></div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end">
                <h3 class="text-2xl font-bold text-white p-6">Galle Fort</h3>
              </div>
            </div>
          </div>
          <div class="text-center mt-12"><a
              class="inline-block px-8 py-4 bg-teal-600 text-white text-base font-semibold rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer"
              href="customer/marketplace.php" data-discover="true">Explore All Destinations</a></div>
        </div>
      </section>
      <section id="contact" class="py-20 px-4">
        <div class="max-w-3xl mx-auto">
          <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Get In Touch</h2>
            <p class="text-lg text-gray-600">Have questions? We're here to help you plan your perfect trip</p>
          </div>
          <form action="#" method="POST" class="bg-white p-8 rounded-2xl border border-gray-200 shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div><label class="block text-sm font-medium text-gray-700 mb-2">Name</label><input required=""
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm"
                  placeholder="Your name" type="text" name="name"></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-2">Email</label><input required=""
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm"
                  placeholder="your@email.com" type="email" name="email"></div>
            </div>
            <div class="mb-6"><label class="block text-sm font-medium text-gray-700 mb-2">Message</label><textarea
                name="message" required="" maxlength="500" rows="5"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm resize-none"
                placeholder="Tell us about your travel plans..."></textarea>
              <p class="text-xs text-gray-500 mt-1">Maximum 500 characters</p>
            </div><button type="submit"
              class="w-full px-6 py-4 bg-teal-600 text-white text-base font-semibold rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer">Send
              Message</button>
          </form>
        </div>
      </section>

<?php include 'includes/footer.php'; ?>
