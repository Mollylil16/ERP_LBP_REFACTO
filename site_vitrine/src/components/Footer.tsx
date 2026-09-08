import React from 'react';

export const Footer: React.FC = () => {
  return (
    <footer className="bg-[#0C2A4A] text-white pt-16 pb-12 border-t border-white/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-white/10">
          {/* Brand & Description */}
          <div className="lg:col-span-2 space-y-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-white text-[#0C2A4A] flex items-center justify-center font-black text-sm">
                LBP
              </div>
              <span className="font-extrabold text-lg tracking-tight text-white">
                LA BELLE PORTE TRANSIT
              </span>
            </div>
            <p className="text-xs text-slate-300 leading-relaxed max-w-sm">
              Commissionnaire agréé en Douane & Transitaire International. Solutions de fret aérien et maritime directes entre la Côte d'Ivoire, le Sénégal, la France et le Canada.
            </p>
            <div className="text-xs text-emerald-400 font-semibold">
              ● Agrément Douane N° 8492/LBP · Certification Qualité ISO 9001
            </div>
          </div>

          {/* Quick Links */}
          <div className="space-y-3">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">Services</h4>
            <ul className="space-y-2 text-xs text-slate-400">
              <li><a href="#services" className="hover:text-white transition-colors">Fret Aérien Express</a></li>
              <li><a href="#services" className="hover:text-white transition-colors">Conteneur Maritime FCL/LCL</a></li>
              <li><a href="#services" className="hover:text-white transition-colors">Dédouanement & Douane</a></li>
              <li><a href="#services" className="hover:text-white transition-colors">Entreposage & Stockage</a></li>
            </ul>
          </div>

          {/* Agences */}
          <div className="space-y-3">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">Réseau d'Agences</h4>
            <ul className="space-y-2 text-xs text-slate-400">
              <li><span className="text-white font-medium">Abidjan :</span> Adjamé & Port-Bouët</li>
              <li><span className="text-white font-medium">Dakar :</span> Avenue Malick Sy</li>
              <li><span className="text-white font-medium">Paris :</span> Bobigny & Roissy CDG</li>
              <li><span className="text-white font-medium">Montréal :</span> Cargo YUL</li>
            </ul>
          </div>

          {/* Legal / ERP */}
          <div className="space-y-3">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">Espace Pro</h4>
            <ul className="space-y-2 text-xs text-slate-400">
              <li><a href="/login" className="hover:text-white transition-colors">Connexion ERP Personnel</a></li>
              <li><a href="#tracking" className="hover:text-white transition-colors">Suivi de Colis & Récépissés</a></li>
              <li><a href="#contact" className="hover:text-white transition-colors">Demande de Cotation</a></li>
              <li><a href="/privacy" className="hover:text-white transition-colors">Mentions Légales & CGV</a></li>
            </ul>
          </div>
        </div>

        <div className="pt-8 flex flex-wrap items-center justify-between gap-4 text-xs text-slate-400">
          <p>© {new Date().getFullYear()} LA BELLE PORTE TRANSIT (ERP LBP). Tous droits réservés.</p>
          <div className="flex items-center gap-6">
            <span>Abidjan · Dakar · Paris · Montréal</span>
          </div>
        </div>
      </div>
    </footer>
  );
};
