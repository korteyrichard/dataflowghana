import React from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useForm } from "@inertiajs/react";
import { DropdownMenuItem } from "@/components/ui/dropdown-menu";

interface EditRoleDialogProps {
  user: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
}

const EditRoleDialog: React.FC<EditRoleDialogProps> = ({ user }) => {
  const { data, setData, put, processing, errors } = useForm({
    role: user.role,
  });

  const submit = (e:any) => {
    e.preventDefault();
    put(route("admin.users.updateRole", { user: user.id }));
  };

  return (
    <Dialog>
      <DialogTrigger asChild>
        <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
          Change Role
        </DropdownMenuItem>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Edit Role for {user.name}</DialogTitle>
          <DialogDescription>
            Select a new role for the user.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit}>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor="role" className="text-right">
                Role
              </Label>
              <select
                id="role"
                value={data.role}
                onChange={(e) => setData("role", e.target.value)}
                className="col-span-3 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
              >
                <option value="customer">Customer</option>
                <option value="agent">Agent</option>
                <option value="dealer">Dealer</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              Save changes
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default EditRoleDialog;