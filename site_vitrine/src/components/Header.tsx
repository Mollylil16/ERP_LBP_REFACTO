import React from 'react';

interface HeaderProps {
  onNavigate?: (sectionId: string) => void;
}

export const Header: React.FC<HeaderProps> = ({ onNavigate }) => {
  return (
    <header className="sticky top-0 z-50 bg-[#FAF9F6]/90 backdrop-blur-md border-b border-slate-200/80">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        {/* Brand Logo */}
        <div className="flex items-center gap-3 cursor-pointer" onClick={() => onNavigate?.('hero')}>
          <div className="w-10 h-10 rounded-xl bg-[#0C2A4A] text-white flex items-center justify-center font-black text-sm shadow-md">
            LBP
          </div>
          <div>
            <span className="font-extrabold text-base tracking-tight text-[#0C2A4A] block leading-tight">
              LA BELLE PORTE TRANSIT
            </span>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">
              Afrique · Europe · Amériques
            </span>
          </div>
        </div>

        {/* Navigation Menu Links */}
        <nav className="hidden md:flex items-center gap-8 text-xs font-bold uppercase tracking-wider text-slate-700">
          <button onClick={() => onNavigate?.('map')} className="hover:text-[#0C2A4A] transition-colors">
            Réseau d'Agences
          </button>
          <button onClick={() => onNavigate?.('services')} className="hover:text-[#0C2A4A] transition-colors">
            Services
          </button>
          <button onClick={() => onNavigate?.('tracking')} className="hover:text-[#0C2A4A] transition-colors">
            Suivi Colis
          </button>
          <button onClick={() => onNavigate?.('contact')} className="hover:text-[#0C2A4A] transition-colors">
            Contact
          </button>
        </nav>

        {/* ERP Login Button */}
        <div className="flex items-center gap-3">
          <a
            href="/login"
            className="px-5 py-2.5 rounded-xl bg-[#0C2A4A] hover:bg-[#091F38] text-white font-bold text-xs shadow-md transition-all flex items-center gap-2"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            Portail Client & ERP
          </a>
        </div>
      </div>
    </header>
  );
};
