import { Composition } from "remotion";
import { GrandPlusReel } from "./GrandPlusReel";

export const Root = () => {
  return (
    <>
      <Composition
        id="GrandPlusReel"
        component={GrandPlusReel}
        durationInFrames={450}
        fps={30}
        width={1080}
        height={1920}
      />
    </>
  );
};