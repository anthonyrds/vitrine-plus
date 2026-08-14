import { ArrowUpRight } from "lucide-react";
import { Link } from "react-router-dom";

export default function ServiceCard({ number, title, text, to }: { number: string; title: string; text: string; to: string }) {
  return (
    <Link to={to} className="group min-h-[280px] border border-black/10 bg-white p-7 transition duration-500 hover:-translate-y-1 hover:bg-[#080808] hover:text-white sm:p-9">
      <div className="flex items-start justify-between">
        <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">{number}</span>
        <ArrowUpRight className="transition-transform duration-500 group-hover:translate-x-1 group-hover:-translate-y-1" />
      </div>
      <div className="mt-20">
        <h3 className="display text-2xl font-extrabold">{title}</h3>
        <p className="mt-3 max-w-sm leading-7 text-black/50 transition group-hover:text-white/55">{text}</p>
      </div>
    </Link>
  );
}