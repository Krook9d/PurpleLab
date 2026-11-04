import { Button, Typography } from "@mui/material"
import PlayArrowOutlined from "@mui/icons-material/PlayArrowOutlined"
import ScienceOutlined from "@mui/icons-material/ScienceOutlined"
import { Card } from "@/components/common/Card"
import { DataTable, type DataTableColumn } from "@/components/common/DataTable"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useAtomicTests, useExecuteAtomicTest } from "@/hooks/useMitre"
import type { AtomicTestOut } from "@/lib/types"

const testNumberOf = (test: AtomicTestOut) => (test.test ? parseInt(test.test, 10) : undefined)

export function AtomicTestsTable({ techniqueId }: { techniqueId: string }) {
  const atomicTests = useAtomicTests(techniqueId)
  const executeAtomic = useExecuteAtomicTest()

  const columns: DataTableColumn<AtomicTestOut>[] = [
    { id: "test", label: "#", width: 48, render: (t) => t.test ?? "—", sortValue: (t) => testNumberOf(t), mono: true },
    { id: "name", label: "Test", render: (t) => t.test_name ?? "—", sortValue: (t) => t.test_name },
    { id: "executor", label: "Executor", width: 140, render: (t) => t.executor_name ?? "—", sortValue: (t) => t.executor_name, mono: true },
    {
      id: "action",
      label: "",
      align: "right",
      width: 130,
      render: (t) => {
        const running = executeAtomic.isPending && executeAtomic.variables?.testNumber === testNumberOf(t)
        return (
          <Button
            size="small"
            variant="outlined"
            startIcon={<PlayArrowOutlined />}
            loading={running}
            loadingPosition="start"
            disabled={executeAtomic.isPending}
            onClick={() => executeAtomic.mutate({ techniqueId, testNumber: testNumberOf(t) })}
          >
            Execute
          </Button>
        )
      },
    },
  ]

  const empty = !atomicTests.isLoading && !atomicTests.data?.length

  return (
    <Card title="Atomic Red Team tests" padding={empty ? "default" : "small"}>
      {empty ? (
        <WidgetNoData
          icon={<ScienceOutlined sx={{ fontSize: 28, opacity: 0.6 }} />}
          message={
            <>
              No Atomic Red Team test indexed for this technique.
              <Typography component="span" variant="body2" sx={{ display: "block", color: "text.tertiary", mt: 0.5 }}>
                The upstream project only covers a subset of ATT&CK techniques: this one simply has no test yet.
              </Typography>
            </>
          }
        />
      ) : (
        <DataTable
          columns={columns}
          rows={atomicTests.data}
          loading={atomicTests.isLoading}
          getRowId={(t) => t.id}
          defaultSort={{ columnId: "test", direction: "asc" }}
        />
      )}
    </Card>
  )
}
