import { lazy, Suspense } from "react";
import { Routes, Route } from "react-router-dom";
import Layout from "./components/Layout";

const Home = lazy(() => import("./pages/Home"));
const Services = lazy(() => import("./pages/Services"));
const Web = lazy(() => import("./pages/Web"));
const SEO = lazy(() => import("./pages/SEO"));
const AI = lazy(() => import("./pages/AI"));
const Realisations = lazy(() => import("./pages/Realisations"));
const Solutions = lazy(() => import("./pages/Solutions"));
const About = lazy(() => import("./pages/About"));
const Audit = lazy(() => import("./pages/Audit"));
const Booking = lazy(() => import("./pages/Booking"));
const Contact = lazy(() => import("./pages/Contact"));
const Legal = lazy(() => import("./pages/Legal"));
const NotFound = lazy(() => import("./pages/NotFound"));

function PageLoader() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center bg-white">
      <div className="h-5 w-5 animate-spin rounded-full border-2 border-black/10 border-t-[#c8a45d]" />
    </div>
  );
}

export default function App() {
  return (
    <Suspense fallback={<PageLoader />}>
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
          <Route path="/rendez-vous" element={<Booking />} />

          <Route path="/contact" element={<Contact />} />
          <Route path="/mentions-legales" element={<Legal />} />

          <Route path="*" element={<NotFound />} />
        </Route>
      </Routes>
    </Suspense>
  );
}