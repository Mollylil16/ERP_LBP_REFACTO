import React from 'react';
import { SERVICES } from '../data/agencies';

export const ServicesGrid: React.FC = () => {
  return (
    <section className="py-20 bg-white text-slate-900 border-t border-slate-200/80">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto mb-16 space-y-3">
          <span className="text-xs font-extrabold uppercase tracking-widest text-[#0C2A4A] bg-slate-100 px-3 py-1 rounded-full">
            Expertise & Solutions
          </span>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-[#0C2A4A] tracking-tight">
            Services Logistiques Intégrés
          </h2>
          <p className="text-slate-600 text-base sm:text-lg">
            Une prise en charge de bout en bout de vos flux de marchandises entre l'Afrique, l'Europe et l'Amérique du Nord.
          </p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
          {SERVICES.map((service) => (
            <div
              key={service.id}
              className="bg-[#FAF9F6] p-7 rounded-2xl border border-slate-200/90 hover:border-[#0C2A4A]/40 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group"
            >
              <div>
                <div className="w-12 h-12 rounded-xl bg-[#0C2A4A] text-white flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                  {service.icon === 'plane' && (
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                  )}
                  {service.icon === 'ship' && (
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                  )}
                  {service.icon === 'file-check' && (
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  )}
                  {service.icon === 'warehouse' && (
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                  )}
                </div>

                <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1 block">
                  {service.category}
                </span>

                <h3 className="text-xl font-bold text-[#0C2A4A] mb-3 leading-snug">
                  {service.title}
                </h3>

                <p className="text-sm text-slate-600 mb-6 leading-relaxed">
                  {service.description}
                </p>
              </div>

              <ul className="space-y-2 pt-4 border-t border-slate-200/70 text-xs font-medium text-slate-700">
                {service.features.map((feat, idx) => (
                  <li key={idx} className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>{feat}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};
