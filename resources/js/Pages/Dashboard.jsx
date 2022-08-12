import React, { useEffect, useState } from 'react';
import Authenticated from '@/Layouts/Authenticated';
import { Head, useForm } from '@inertiajs/inertia-react';
import axios from 'axios';

export default function Dashboard(props) {
    const [ reports, setReports ] = useState([]);

    const { data, setData, post, processing, errors, reset } = useForm({
        supporter_id: null
    });

    function handleApprove(id) {
        setData("supporter_id", id);
        post(route('approveReport'));
    }

    useEffect(() => {
        axios.get('/api/laporan')
        .then((e) => {
            setReports(e.data);
        })
        .catch(console.error)
    }, [])

    return (
        <Authenticated
            auth={props.auth}
            errors={props.errors}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12 px-5 md:px-32 flex flex-col gap-5">
                <div className="sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">Hello, {props.auth.user.name.split(" ")[0]}!</div>
                    </div>
                </div>

                <div className="sm:px-6 lg:px-8">
                    <div className="bg-white shadow-sm sm:rounded-lg flex flex-col gap-3">
                        <h2 className="p-6 bg-white border-b border-gray-200 font-semibold text-2xl">Reports</h2>
                        <div className="flex flex-col gap-2 py-2">
                            {
                                reports.length > 0 ?
                                reports.map((e, i) => {
                                    
                                    return (
                                        <div key={i} className="px-5 hover:bg-slate-200 flex items-start py-2">
                                            <span className='w-1/6'>{e.name}</span>
                                            <span className='w-1/6'>{e.position}</span>
                                            <span className='w-1/6'>{e.department}</span>
                                            <span className='w-1/6'>{e.party}</span>
                                            <div className='w-2/6 flex gap-2 items-center'>
                                                <a href={e.evidences.url} className="bg-rose-500 rounded-xl px-3 py-2 text-white">View Evidence</a>
                                                <button className="bg-emerald-500 rounded-xl px-3 py-2 text-white" psesupporter-id={e.id} onClick={()=>{handleApprove(e.id)}}>Approve</button>
                                            </div>
                                        </div>
                                    )
                                }) : <div className="px-5 py-2">
                                    No report
                                </div>
                            }
                        </div>
                    </div>
                </div>
            </div>
        </Authenticated>
    );
}
