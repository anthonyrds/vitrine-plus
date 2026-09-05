import React from "react";
import {
  AbsoluteFill,
  Easing,
  interpolate,
  spring,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";

const GOLD = "#C8A45D";
const WHITE = "#FFFFFF";
const MUTED = "rgba(255,255,255,0.52)";
const BLACK = "#080808";

function FadeIn({
  children,
  start,
  duration = 18,
  y = 0,
}: {
  children: React.ReactNode;
  start: number;
  duration?: number;
  y?: number;
}) {
  const frame = useCurrentFrame();

  const opacity = interpolate(
    frame,
    [start, start + duration],
    [0, 1],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  const translateY = interpolate(
    frame,
    [start, start + duration],
    [y, 0],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  return (
    <div
      style={{
        opacity,
        transform: `translateY(${translateY}px)`,
      }}
    >
      {children}
    </div>
  );
}

function RevealWord({
  children,
  start,
  delay = 0,
  fontSize,
}: {
  children: React.ReactNode;
  start: number;
  delay?: number;
  fontSize: number;
}) {
  const frame = useCurrentFrame();

  const localStart = start + delay;

  const progress = interpolate(
    frame,
    [localStart, localStart + 20],
    [0, 1],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  const opacity = progress;

  const translateY = interpolate(
    progress,
    [0, 1],
    [55, 0]
  );

  const scale = interpolate(
    progress,
    [0, 1],
    [0.94, 1]
  );

  return (
    <span
      style={{
        display: "inline-block",
        opacity,
        transform: `translateY(${translateY}px) scale(${scale})`,
        fontSize,
        lineHeight: 0.88,
        letterSpacing: "-0.065em",
      }}
    >
      {children}
    </span>
  );
}

function GoldLine({
  start,
  width = 120,
}: {
  start: number;
  width?: number;
}) {
  const frame = useCurrentFrame();

  const progress = interpolate(
    frame,
    [start, start + 24],
    [0, 1],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  return (
    <div
      style={{
        width: width * progress,
        height: 2,
        backgroundColor: GOLD,
      }}
    />
  );
}

function SceneOne() {
  const frame = useCurrentFrame();

  const scale = interpolate(
    frame,
    [0, 45],
    [0.96, 1],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        justifyContent: "center",
        alignItems: "center",
        transform: `scale(${scale})`,
      }}
    >
      <div
        style={{
          position: "absolute",
          top: 100,
          left: 70,
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 18,
          fontWeight: 800,
          letterSpacing: "0.24em",
          color: GOLD,
        }}
      >
        NOUVEAU
      </div>

      <div
        style={{
          position: "absolute",
          top: 140,
          left: 70,
          width: 42,
          height: 2,
          backgroundColor: GOLD,
        }}
      />

      <FadeIn start={8} duration={28} y={35}>
        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 52,
            fontWeight: 700,
            letterSpacing: "-0.045em",
            color: WHITE,
          }}
        >
          Vitrine
          <span style={{ color: GOLD }}>+</span>
        </div>
      </FadeIn>

      <div
        style={{
          position: "absolute",
          bottom: 105,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 13,
          fontWeight: 700,
          letterSpacing: "0.16em",
          color: "rgba(255,255,255,0.32)",
          textTransform: "uppercase",
        }}
      >
        <span>Votre entreprise. En mieux.</span>
        <span>01 / 05</span>
      </div>
    </AbsoluteFill>
  );
}

function SceneTwo() {
  const frame = useCurrentFrame();

  const plusScale = spring({
    frame: Math.max(0, frame - 42),
    fps: 30,
    config: {
      damping: 16,
      stiffness: 130,
      mass: 0.7,
    },
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        padding: "150px 70px",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          position: "absolute",
          top: 90,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "space-between",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 13,
          fontWeight: 700,
          letterSpacing: "0.18em",
          color: "rgba(255,255,255,0.35)",
        }}
      >
        <span>LE GRAND +</span>
        <span>02 / 05</span>
      </div>

      <FadeIn start={4} duration={22} y={30}>
        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 20,
            fontWeight: 800,
            textTransform: "uppercase",
            letterSpacing: "0.3em",
            color: GOLD,
            marginBottom: 45,
          }}
        >
          Vitrine+
        </div>
      </FadeIn>

      <div
        style={{
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 172,
          fontWeight: 900,
          lineHeight: 0.8,
          letterSpacing: "-0.09em",
          color: WHITE,
        }}
      >
        <RevealWord start={12} fontSize={172}>
          LE
        </RevealWord>
        <br />
        <RevealWord start={18} delay={5} fontSize={172}>
          GRAND
        </RevealWord>
        <span
          style={{
            display: "inline-block",
            marginLeft: 12,
            color: GOLD,
            transform: `scale(${plusScale})`,
            transformOrigin: "bottom left",
          }}
        >
          +
        </span>
      </div>

      <FadeIn start={65} duration={20} y={25}>
        <div
          style={{
            marginTop: 65,
            display: "flex",
            alignItems: "center",
            gap: 22,
          }}
        >
          <GoldLine start={65} width={95} />

          <span
            style={{
              fontFamily: "Arial, Helvetica, sans-serif",
              fontSize: 18,
              fontWeight: 600,
              color: MUTED,
              letterSpacing: "0.02em",
            }}
          >
            Une opération Vitrine+
          </span>
        </div>
      </FadeIn>
    </AbsoluteFill>
  );
}

function SceneThree() {
  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        padding: "150px 70px",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          position: "absolute",
          top: 90,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "space-between",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 13,
          fontWeight: 700,
          letterSpacing: "0.18em",
          color: "rgba(255,255,255,0.35)",
        }}
      >
        <span>LE GRAND +</span>
        <span>03 / 05</span>
      </div>

      <div
        style={{
          fontFamily: "Arial, Helvetica, sans-serif",
          fontWeight: 900,
          color: WHITE,
          letterSpacing: "-0.075em",
          lineHeight: 0.9,
        }}
      >
        <RevealWord start={4} fontSize={106}>
          ET SI VOTRE
        </RevealWord>

        <br />

        <RevealWord start={12} delay={6} fontSize={106}>
          SITE ÉTAIT
        </RevealWord>

        <br />

        <span
          style={{
            display: "inline-block",
            marginTop: 10,
          }}
        >
          <RevealWord start={22} delay={10} fontSize={126}>
            LE PROCHAIN
          </RevealWord>

          <span
            style={{
              color: GOLD,
              marginLeft: 10,
              fontSize: 126,
            }}
          >
            ?
          </span>
        </span>
      </div>

      <FadeIn start={82} duration={22} y={30}>
        <div
          style={{
            marginTop: 70,
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 19,
            lineHeight: 1.55,
            color: MUTED,
            maxWidth: 760,
          }}
        >
          Chaque mois, une entreprise est sélectionnée
          pour bénéficier d'une refonte complète de son site.
        </div>
      </FadeIn>
    </AbsoluteFill>
  );
}

function SceneFour() {
  const frame = useCurrentFrame();

  const numberScale = spring({
    frame: Math.max(0, frame - 25),
    fps: 30,
    config: {
      damping: 14,
      stiffness: 110,
      mass: 0.7,
    },
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        padding: "150px 70px",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          position: "absolute",
          top: 90,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "space-between",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 13,
          fontWeight: 700,
          letterSpacing: "0.18em",
          color: "rgba(255,255,255,0.35)",
        }}
      >
        <span>LE GRAND +</span>
        <span>04 / 05</span>
      </div>

      <FadeIn start={0} duration={20} y={25}>
        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 19,
            fontWeight: 800,
            textTransform: "uppercase",
            letterSpacing: "0.28em",
            color: GOLD,
          }}
        >
          Chaque mois
        </div>
      </FadeIn>

      <div
        style={{
          marginTop: 35,
          display: "flex",
          alignItems: "baseline",
          gap: 25,
          transform: `scale(${numberScale})`,
          transformOrigin: "left center",
        }}
      >
        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 210,
            fontWeight: 900,
            lineHeight: 0.8,
            letterSpacing: "-0.1em",
            color: WHITE,
          }}
        >
          1
        </div>

        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 55,
            fontWeight: 700,
            lineHeight: 1,
            letterSpacing: "-0.04em",
            color: WHITE,
          }}
        >
          entreprise
        </div>
      </div>

      <FadeIn start={42} duration={22} y={30}>
        <div
          style={{
            marginTop: 65,
            display: "grid",
            gridTemplateColumns: "1fr",
            gap: 25,
          }}
        >
          {[
            "1 refonte complète",
            "1 expérience sur mesure",
            "100 % offerte",
          ].map((text, index) => (
            <div
              key={text}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 18,
                fontFamily: "Arial, Helvetica, sans-serif",
                fontSize: 25,
                fontWeight: index === 2 ? 800 : 600,
                color: index === 2 ? WHITE : MUTED,
              }}
            >
              <span
                style={{
                  width: 8,
                  height: 8,
                  borderRadius: 999,
                  backgroundColor: GOLD,
                  flexShrink: 0,
                }}
              />
              {text}
            </div>
          ))}
        </div>
      </FadeIn>
    </AbsoluteFill>
  );
}

function SceneFive() {
  const frame = useCurrentFrame();

  const ctaProgress = interpolate(
    frame,
    [42, 70],
    [0, 1],
    {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.out(Easing.cubic),
    }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        padding: "120px 70px",
        justifyContent: "center",
        alignItems: "center",
        textAlign: "center",
      }}
    >
      <div
        style={{
          position: "absolute",
          top: 90,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "space-between",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 13,
          fontWeight: 700,
          letterSpacing: "0.18em",
          color: "rgba(255,255,255,0.35)",
        }}
      >
        <span>LE GRAND +</span>
        <span>05 / 05</span>
      </div>

      <FadeIn start={0} duration={25} y={30}>
        <div
          style={{
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 18,
            fontWeight: 800,
            letterSpacing: "0.28em",
            textTransform: "uppercase",
            color: GOLD,
            marginBottom: 45,
          }}
        >
          Votre tour ?
        </div>
      </FadeIn>

      <div
        style={{
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 116,
          fontWeight: 900,
          lineHeight: 0.86,
          letterSpacing: "-0.075em",
          color: WHITE,
        }}
      >
        <RevealWord start={8} fontSize={116}>
          PARTICIPEZ
        </RevealWord>

        <br />

        <span
          style={{
            color: GOLD,
          }}
        >
          <RevealWord start={18} delay={7} fontSize={116}>
            AU GRAND +
          </RevealWord>
        </span>
      </div>

      <FadeIn start={68} duration={22} y={25}>
        <div
          style={{
            marginTop: 60,
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 20,
            color: MUTED,
            maxWidth: 700,
            lineHeight: 1.5,
          }}
        >
          Chaque mois · 1 entreprise · 1 refonte complète
        </div>
      </FadeIn>

      <div
        style={{
          position: "absolute",
          bottom: 130,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "center",
          opacity: ctaProgress,
          transform: `translateY(${interpolate(
            ctaProgress,
            [0, 1],
            [25, 0]
          )}px)`,
        }}
      >
        <div
          style={{
            border: `1px solid ${GOLD}`,
            padding: "20px 32px",
            fontFamily: "Arial, Helvetica, sans-serif",
            fontSize: 18,
            fontWeight: 800,
            letterSpacing: "0.08em",
            color: WHITE,
          }}
        >
          vitrineplus.fr/le-grand-plus
        </div>
      </div>

      <div
        style={{
          position: "absolute",
          bottom: 55,
          left: 70,
          right: 70,
          display: "flex",
          justifyContent: "center",
          fontFamily: "Arial, Helvetica, sans-serif",
          fontSize: 14,
          fontWeight: 700,
          color: "rgba(255,255,255,0.32)",
          letterSpacing: "0.08em",
        }}
      >
        Vitrine<span style={{ color: GOLD }}>+</span>
      </div>
    </AbsoluteFill>
  );
}

export const GrandPlusReel = () => {
  const frame = useCurrentFrame();

  const scene =
    frame < 45
      ? 1
      : frame < 135
        ? 2
        : frame < 225
          ? 3
          : frame < 330
            ? 4
            : 5;

  return (
    <AbsoluteFill
      style={{
        backgroundColor: BLACK,
        fontFamily: "Arial, Helvetica, sans-serif",
      }}
    >
      {scene === 1 && <SceneOne />}
      {scene === 2 && <SceneTwo />}
      {scene === 3 && <SceneThree />}
      {scene === 4 && <SceneFour />}
      {scene === 5 && <SceneFive />}
    </AbsoluteFill>
  );
};