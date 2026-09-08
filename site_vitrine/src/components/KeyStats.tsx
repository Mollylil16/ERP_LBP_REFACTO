import React from 'react';
import { KEY_STATS } from '../data/agencies';

export const KeyStats: React.FC = () => {
  return (
    <section className="py-16 bg-[#0C2A4A] text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
          {KEY_STATS.map((stat) => (
            <div
              key={stat.id}
              className="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-white/20 transition-all text-left space-y-2"
            >
              <span className="block text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white">
                {stat.value}
              </span>
              <div className="text-sm font-bold text-emerald-400 uppercase tracking-wider">
                {stat.unit}
              </div>
              <h4 className="text-base font-bold text-slate-200">
                {stat.label}
              </h4>
              <p className="text-xs text-slate-400 leading-relaxed">
                {stat.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};
