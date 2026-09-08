import React, { useState } from 'react';

export const LiveTrackingWidget: React.FC = () => {
  const [trackingNumber, setTrackingNumber] = useState('');
  const [searched, setSearched] = useState(false);

  const mockTrackingResult = {
    code: trackingNumber || 'LBP-2026-8849',
    type: 'Fret Aérien Express',
    origin: 'Paris (CDG)',
    destination: 'Abidjan (ABJ)',
    sender: 'SOCIÉTÉ D\'IMPORT AFRIQUE',
    recipient: 'GROUPE LBP DISTRIBUTION',
    status: 'En Transit Aérien',
    estimatedDelivery: '10 Septembre 2026',
    weight: '450.00 kg',
    steps: [
      { title: 'Prise en charge & Emballage', location: 'Hub Paris Bobigny', date: '06/09 09:30', status: 'completed' },
      { title: 'Dédouanement Export Validé', location: 'Douane Roissy CDG', date: '06/09 14:15', status: 'completed' },
      { title: 'Embarquement Cargo AF8490', location: 'Aéroport Paris CDG', date: '07/09 02:00', status: 'current' },
      { title: 'Arrivée & Dédouanement Import', location: 'Aéroport Abidjan Fret', date: 'En attente', status: 'pending' },
      { title: 'Livraison Finale au Destinataire', location: 'Abidjan', date: 'En attente', status: 'pending' }
    ]
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setSearched(true);
  };

  return (
    <section className="py-20 bg-[#FAF9F6] text-slate-900 border-t border-slate-200/80">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-12 space-y-3">
          <span className="text-xs font-extrabold uppercase tracking-widest text-[#0C2A4A] bg-slate-200/70 px-3 py-1 rounded-full">
            Suivi 24/7
          </span>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-[#0C2A4A] tracking-tight">
            Suivez Vos Expéditions en Temps Réel
          </h2>
          <p className="text-slate-600 text-sm sm:text-base">
            Saisissez votre numéro de récépissé, bordereau ou code de suivi LBP pour consulter le statut détaillé.
          </p>
        </div>

        {/* Search Input Box */}
        <form onSubmit={handleSearch} className="max-w-2xl mx-auto mb-10">
          <div className="flex flex-col sm:flex-row gap-3 bg-white p-2 rounded-2xl border border-slate-300 shadow-lg">
            <input
              type="text"
              placeholder="Ex : LBP-2026-8849, PK-90412..."
              value={trackingNumber}
              onChange={(e) => setTrackingNumber(e.target.value)}
              className="flex-1 px-4 py-3.5 text-base font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none"
              required
            />
            <button
              type="submit"
              className="px-7 py-3.5 rounded-xl bg-[#0C2A4A] hover:bg-[#091F38] text-white font-bold text-sm shadow-md transition-all"
            >
              Rechercher l'Expédition
            </button>
          </div>
        </form>

        {/* Live Timeline Result Result Box */}
        <div className="bg-white rounded-3xl border border-slate-200/90 shadow-xl overflow-hidden">
          <div className="bg-[#0C2A4A] text-white p-6 sm:p-8 flex flex-wrap items-center justify-between gap-4">
            <div>
              <span className="text-xs uppercase tracking-wider font-semibold text-slate-300 block mb-1">
                Bordereau : {mockTrackingResult.code}
              </span>
              <h3 className="text-2xl font-bold">
                {mockTrackingResult.origin} ➔ {mockTrackingResult.destination}
              </h3>
            </div>
            <div className="text-right">
              <span className="inline-block px-3 py-1 rounded-full text-xs font-bold bg-emerald-500 text-white mb-1">
                {mockTrackingResult.status}
              </span>
              <p className="text-xs text-slate-300">
                Livraison estimée : <strong className="text-white">{mockTrackingResult.estimatedDelivery}</strong>
              </p>
            </div>
          </div>

          <div className="p-6 sm:p-8 space-y-8">
            {/* Timeline Steps */}
            <div className="relative border-l-2 border-slate-200 ml-4 sm:ml-6 space-y-6">
              {mockTrackingResult.steps.map((step, idx) => (
                <div key={idx} className="relative pl-6 sm:pl-8">
                  <span
                    className={`absolute -left-[9px] top-0.5 w-4 h-4 rounded-full border-2 border-white ${
                      step.status === 'completed'
                        ? 'bg-emerald-500'
                        : step.status === 'current'
                        ? 'bg-blue-600 ring-4 ring-blue-100'
                        : 'bg-slate-300'
                    }`}
                  ></span>

                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <h4 className="text-base font-bold text-slate-900">
                      {step.title}
                    </h4>
                    <span className="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded">
                      {step.date}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 mt-1 font-medium">
                    {step.location}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
