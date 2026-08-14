import { ArrowRight } from "lucide-react";
import { Link } from "react-router-dom";

type Props = {
  children: React.ReactNode;
  to?: string;
  href?: string;
  dark?: boolean;
  className?: string;
};

export default function Button({ children, to, href, dark = true, className = "" }: Props) {
  const classes = `focus-ring group inline-flex items-center justify-center gap-2 rounded-full px-6 py-3.5 text-sm font-bold transition duration-300 hover:-translate-y-0.5 ${
    dark ? "bg-[#080808] !text-white hover:bg-[#c8a45d] hover:!text-[#080808]" : "bg-white !text-[#080808] hover:bg-[#c8a45d] hover:!text-[#080808]"
  } ${className}`;

  const content = <>{children}<ArrowRight size={16} className="transition-transform group-hover:translate-x-1" /></>;

  if (to) return <Link to={to} className={classes}>{content}</Link>;
  return <a href={href ?? "#"} className={classes}>{content}</a>;
}