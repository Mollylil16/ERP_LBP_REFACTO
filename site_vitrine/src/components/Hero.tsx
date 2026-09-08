import React from 'react';

interface HeroProps {
  onQuoteClick?: () => void;
  onTrackClick?: () => void;
}

export const Hero: React.FC<HeroProps> = ({ onQuoteClick, onTrackClick }) => {
  return (
    <section className="relative pt-12 pb-16 md:pt-20 md:pb-24 overflow-hidden bg-[#FAF9F6] text-slate-900">
      {/* Subtle Background Pattern */}
      <div className="absolute inset-0 opacity-[0.03] pointer-events-none bg-[radial-gradient(#0C2A4A_1px,transparent_1px)] [background-size:24px_24px]"></div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div className="grid lg:grid-cols-12 gap-12 items-center">
          {/* Hero Copy */}
          <div className="lg:col-span-7 space-y-6 text-left">
            {/* Live Status Pill */}
            <div className="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900 text-white text-xs font-semibold tracking-wide">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Réseau Fret Direct : Abidjan · Dakar · Paris · Montréal</span>
            </div>

            {/* Main Headline */}
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-[#0C2A4A] leading-[1.1]">
              La Logistique de Précision Entre l'Afrique & l'Occident.
            </h1>

            {/* Subtitle */}
            <p className="text-lg sm:text-xl text-slate-600 font-normal leading-relaxed max-w-2xl">
              Fret aérien et maritime haut de gamme, dédouanement garanti et traçabilité intégrale. L'infrastructure logistique de confiance des entreprises exigeantes.
            </p>

            {/* CTA Action Buttons */}
            <div className="pt-2 flex flex-wrap gap-4 items-center">
              <button
                onClick={onQuoteClick}
                className="px-7 py-4 rounded-xl bg-[#0C2A4A] hover:bg-[#091F38] text-white font-bold text-base shadow-lg shadow-[#0C2A4A]/20 transition-all transform hover:-translate-y-0.5"
              >
                Demander un Devis Sur-Mesure
              </button>
              <button
                onClick={onTrackClick}
                className="px-7 py-4 rounded-xl bg-white border border-slate-300 hover:border-slate-400 text-slate-800 font-bold text-base shadow-sm hover:bg-slate-50 transition-all"
              >
                Suivre un Colis ou Expédition
              </button>
            </div>

            {/* Micro Trust Indicators */}
            <div className="pt-6 border-t border-slate-200/80 grid grid-cols-3 gap-4 max-w-lg">
              <div>
                <span className="block text-2xl font-black text-[#0C2A4A]">48h</span>
                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Délai Aérien Paris-Abidjan</span>
              </div>
              <div>
                <span className="block text-2xl font-black text-[#0C2A4A]">99.4%</span>
                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Passage Douane Sans Reliquat</span>
              </div>
              <div>
                <span className="block text-2xl font-black text-[#0C2A4A]">4 Hubs</span>
                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Magasins sous alarme</span>
              </div>
            </div>
          </div>

          {/* Hero Visual Card */}
          <div className="lg:col-span-5 relative">
            <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-2xl space-y-5">
              <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl bg-[#0C2A4A] text-white flex items-center justify-center font-black text-sm">
                    LBP
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 text-sm">LA BELLE PORTE TRANSIT</h3>
                    <p className="text-xs text-slate-500">Commissionnaire agréé en Douane</p>
                  </div>
                </div>
                <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Flux ISO 9001
                </span>
              </div>

              {/* Sample Live Dispatch Status Widget */}
              <div className="space-y-3">
                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                  <div className="space-y-0.5">
                    <span className="font-bold text-slate-900 block">AF-8490 (Fret Aérien Express)</span>
                    <span className="text-slate-500">Roissy CDG ➔ Abidjan Port-Bouët</span>
                  </div>
                  <span className="font-bold text-emerald-600 bg-emerald-100/80 px-2 py-1 rounded">En Vol</span>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                  <div className="space-y-0.5">
                    <span className="font-bold text-slate-900 block">MSK-2041 (Conteneur Groupage)</span>
                    <span className="text-slate-500">Dakar Port ➔ Abidjan Autonome</span>
                  </div>
                  <span className="font-bold text-blue-600 bg-blue-100/80 px-2 py-1 rounded">En Transit</span>
                </div>

                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                  <div className="space-y-0.5">
                    <span className="font-bold text-slate-900 block">YUL-9102 (Transatlantique)</span>
                    <span className="text-slate-500">Montréal YUL ➔ Paris CDG</span>
                  </div>
                  <span className="font-bold text-purple-600 bg-purple-100/80 px-2 py-1 rounded">Dédouané</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
