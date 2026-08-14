import { Routes, Route } from "react-router-dom";
import Layout from "./components/Layout";
import Home from "./pages/Home";
import Services from "./pages/Services";
import Web from "./pages/Web";
import SEO from "./pages/SEO";
import AI from "./pages/AI";
import Realisations from "./pages/Realisations";
import Solutions from "./pages/Solutions";
import About from "./pages/About";
import Audit from "./pages/Audit";
import Contact from "./pages/Contact";
import Legal from "./pages/Legal";
import NotFound from "./pages/NotFound";

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={<Home />} />
        <Route path="/services" element={<Services />} />
        <Route path="/services/web" element={<Web />} />
        <Route path="/services/seo" element={<SEO />} />
        <Route path="/services/ia" element={<AI />} />
        <Route path="/realisations" element={<Realisations />} />
        <Route path="/solutions" element={<Solutions />} />
        <Route path="/a-propos" element={<About />} />
        <Route path="/audit" element={<Audit />} />
        <Route path="/contact" element={<Contact />} />
        <Route path="/mentions-legales" element={<Legal />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  );
}