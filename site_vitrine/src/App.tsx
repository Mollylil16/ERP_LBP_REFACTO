import React from 'react';
import { Header } from './components/Header';
import { Hero } from './components/Hero';
import { AgencyFlowMap } from './components/AgencyFlowMap';
import { KeyStats } from './components/KeyStats';
import { ServicesGrid } from './components/ServicesGrid';
import { LiveTrackingWidget } from './components/LiveTrackingWidget';
import { ContactCTA } from './components/ContactCTA';
import { Footer } from './components/Footer';

export default function App() {
  return (
    <div className="min-h-screen bg-[#FAF9F6] text-[#0C2A4A] font-sans antialiased selection:bg-[#0C2A4A] selection:text-white">
      {/* 1. Header Navigation */}
      <Header />

      {/* 2. Hero Section */}
      <main>
        <Hero />

        {/* 3. Interactive Agency Flow Map Section */}
        <section id="network" className="py-16 md:py-24 bg-[#0C2A4A] text-white overflow-hidden relative">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div className="text-center max-w-3xl mx-auto mb-10">
              <span className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-4">
                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Couverture Intercontinentale
              </span>
              <h2 className="text-3xl md:text-5xl font-extrabold tracking-tight text-white mb-4">
                Flux Logistiques Directs entre Nos 4 Hubs
              </h2>
              <p className="text-slate-300 text-base md:text-lg">
                Visualisez la circulation en temps réel de vos marchandises entre la Côte d'Ivoire, le Sénégal, la France et le Canada via nos routes aériennes et maritimes dédiées.
              </p>
            </div>

            {/* Isolated Flow Map Component */}
            <AgencyFlowMap />
          </div>
        </section>

        {/* 4. Key Performance Metrics */}
        <KeyStats />

        {/* 5. Integrated Services Grid */}
        <ServicesGrid />

        {/* 6. Live Tracking Widget */}
        <LiveTrackingWidget />

        {/* 7. Contact & Quotation CTA */}
        <ContactCTA />
      </main>

      {/* 8. Footer */}
      <Footer />
    </div>
  );
}
