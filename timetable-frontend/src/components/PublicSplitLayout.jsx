import { publicAssetUrl } from '../utils/appPaths';

export function PublicSplitLayout({
  visualTitle = 'Welcome!',
  visualText = 'University of Rwanda Timetable System.',
  children,
}) {
  const buildingUrl = publicAssetUrl('ur_building.jpeg');

  return (
    <div className="min-h-screen bg-[#eef1f6] overflow-x-hidden font-[Segoe_UI,Tahoma,Geneva,Verdana,sans-serif]">
      <div className="flex flex-col lg:flex-row min-h-screen">
        <section className="w-full lg:w-1/2 bg-white flex flex-col justify-center px-5 py-8 sm:px-8 lg:px-10 order-2 lg:order-1">
          <div className="w-full max-w-[400px] mx-auto">{children}</div>
        </section>

        <aside
          className="relative w-full lg:w-1/2 min-h-[220px] sm:min-h-[280px] lg:min-h-screen flex items-stretch px-4 py-7 sm:px-8 lg:px-10 order-1 lg:order-2 bg-[#1e3c72] bg-cover bg-center"
          style={{ backgroundImage: `url(${buildingUrl})` }}
        >
          <div
            className="absolute inset-0"
            style={{
              background:
                'linear-gradient(160deg, rgba(30,60,114,0.42) 0%, rgba(42,82,152,0.58) 45%, rgba(22,45,90,0.72) 100%)',
            }}
            aria-hidden="true"
          />
          <div className="relative z-[1] w-full max-w-[480px] my-auto text-left text-white">
            <p className="text-xs uppercase tracking-[0.18em] font-semibold opacity-90 mb-3">
              University of Rwanda
            </p>
            <h2 className="text-2xl sm:text-3xl lg:text-[2.15rem] font-bold mb-2 sm:mb-3 drop-shadow leading-tight">
              {visualTitle}
            </h2>
            <p className="hidden sm:block text-sm sm:text-[0.95rem] leading-relaxed opacity-95 mb-5 max-w-[420px]">
              {visualText}
            </p>
          </div>
        </aside>
      </div>
    </div>
  );
}
