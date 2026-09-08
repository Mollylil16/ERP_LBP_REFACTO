import React, { useState } from 'react';

export const ContactCTA: React.FC = () => {
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitted(true);
  };

  return (
    <section className="py-20 bg-white text-slate-900 border-t border-slate-200" id="contact">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-12 gap-12 items-center">
          {/* Left Info Column */}
          <div className="lg:col-span-5 space-y-6">
            <span className="text-xs font-extrabold uppercase tracking-widest text-[#0C2A4A] bg-slate-100 px-3 py-1 rounded-full">
              Demande d'Cotation
            </span>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-[#0C2A4A] tracking-tight leading-tight">
              Prêt à Optimiser Vos Flux de Marchandises ?
            </h2>
            <p className="text-slate-600 text-base leading-relaxed">
              Obtenez une étude tarifaire personnalisée sous 24 heures de la part de nos experts transitaires basés à Abidjan, Dakar, Paris et Montréal.
            </p>

            <div className="space-y-4 pt-4 border-t border-slate-200 text-sm">
              <div className="flex items-start gap-3">
                <span className="w-8 h-8 rounded-lg bg-[#0C2A4A] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                  CI
                </span>
                <div>
                  <strong className="block text-slate-900">Hub Abidjan (Côte d'Ivoire)</strong>
                  <span className="text-slate-500">Adjamé & Aéroport Port-Bouët · +225 27 20 00 11 22</span>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <span className="w-8 h-8 rounded-lg bg-[#0C2A4A] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                  FR
                </span>
                <div>
                  <strong className="block text-slate-900">Hub Paris (France)</strong>
                  <span className="text-slate-500">Bobigny & Roissy CDG · +33 1 48 30 90 00</span>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <span className="w-8 h-8 rounded-lg bg-[#0C2A4A] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                  CA
                </span>
                <div>
                  <strong className="block text-slate-900">Hub Montréal (Canada)</strong>
                  <span className="text-slate-500">Cargo YUL · +1 514 870 33 44</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right Form Card */}
          <div className="lg:col-span-7">
            <div className="bg-[#FAF9F6] p-8 sm:p-10 rounded-3xl border border-slate-200 shadow-xl">
              {submitted ? (
                <div className="text-center py-12 space-y-4">
                  <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl font-bold">
                    ✓
                  </div>
                  <h3 className="text-2xl font-bold text-[#0C2A4A]">Demande Enregistrée !</h3>
                  <p className="text-slate-600 text-sm max-w-md mx-auto">
                    Merci. Un conseiller commercial dédié étudie votre cotation et vous contactera sous 24 heures.
                  </p>
                  <button
                    onClick={() => setSubmitted(false)}
                    className="px-6 py-2.5 rounded-xl bg-[#0C2A4A] text-white font-bold text-xs"
                  >
                    Envoyer une autre demande
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-5">
                  <h3 className="text-xl font-extrabold text-[#0C2A4A] mb-4">
                    Formulaire de Cotation Express
                  </h3>

                  <div className="grid sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nom complet / Entreprise *</label>
                      <input type="text" required placeholder="Société ou Nom" className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white" />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Email professionnel *</label>
                      <input type="email" required placeholder="contact@entreprise.com" className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white" />
                    </div>
                  </div>

                  <div className="grid sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Type de Fret</label>
                      <select className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white">
                        <option>Fret Aérien Express</option>
                        <option>Fret Aérien Economy</option>
                        <option>Conteneur Maritime (FCL)</option>
                        <option>Groupage Maritime (LCL)</option>
                        <option>Transit & Dédouanement</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Ville Départ</label>
                      <select className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white">
                        <option>Abidjan (CI)</option>
                        <option>Dakar (SN)</option>
                        <option>Paris (FR)</option>
                        <option>Montréal (CA)</option>
                        <option>Autre provenance</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Ville Arrivée</label>
                      <select className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white">
                        <option>Paris (FR)</option>
                        <option>Abidjan (CI)</option>
                        <option>Dakar (SN)</option>
                        <option>Montréal (CA)</option>
                        <option>Autre destination</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">Description de la marchandise & Volume estimé</label>
                    <textarea rows={3} placeholder="Poids estimé, nombre de colis, dimensions, quittance douane..." className="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:border-[#0C2A4A] text-sm font-semibold bg-white"></textarea>
                  </div>

                  <button
                    type="submit"
                    className="w-full py-4 rounded-xl bg-[#0C2A4A] hover:bg-[#091F38] text-white font-bold text-base shadow-lg shadow-[#0C2A4A]/20 transition-all"
                  >
                    Obtenir ma Cotation Gratuitement
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
