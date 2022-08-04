import { useEffect } from "react"
import { TbFlag, TbFlagOff } from "react-icons/all"
export default function Figure({ picture, name, position, department, evidence, party, quote }) {
    
    return (
        <div className="flex flex-col px-3 py-4 md:px-5 md:py-5 gap-4 rounded-lg hover:shadow-xl hover:shadow-rose-300 bg-white w-lg md:w-80 flex-shrink h-fit transition-all duration-300">
            <div className="flex flex-row gap-3 items-center">
                {
                    picture ?
                    <div className="w-12 h-12 rounded-full flex items-center justify-center bg-cover bg-no-repeat" style={{backgroundImage: `url(${picture})`}}>
                        {/* <img src={picture} alt={name} /> */}
                    </div> :
                    <div className="w-12 h-12 rounded-full flex items-center justify-center bg-gray-800 text-white">
                        ?
                    </div>
                }
                <div className="flex flex-col">
                    <h4 className="text-sm font-semibold">{name}</h4>
                    <span className="text-xs">{position}</span>
                    <span className="text-xs">{department}</span>
                </div>
            </div>
            {
                !party || party === "independen" || party === "independent" ?
                <span className="text-xs font-semibold text-slate-500 flex flex-row gap-1 items-center"><TbFlagOff /> Figur ini tidak terafiliasi dengan partai politik</span>
                : <span className="text-xs font-semibold text-slate-500 flex flex-row gap-1 items-center"><TbFlag /> Figur ini terafiliasi dengan {party}</span>
            }
            {quote?<i className="font-serif">"{quote}"</i>:null}
            {
                evidence.length > 0 ? evidence.map((e, i) => {
                    if(e.type === "tweet") return (
                        <div>
                            <a key={i} href={evidence} className="bg-sky-500 rounded-lg hover:shadow-xl hover:shadow-sky-200 text-white px-3 py-2 w-fit">Lihat Tweet</a>
                        </div>
                    )
                    else if(e.type === "berita") return (
                        <div>
                            <a key={i} href={evidence} className="bg-amber-500 rounded-lg hover:shadow-xl hover:shadow-amber-200 text-white px-3 py-2 w-fit">Lihat Berita</a>
                        </div>
                    )
                }) : null
            }
            
        </div>
    )
}